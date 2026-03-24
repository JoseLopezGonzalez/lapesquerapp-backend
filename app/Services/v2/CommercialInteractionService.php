<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Models\CommercialInteraction;
use App\Models\Customer;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialInteractionService
{
    public static function list(Request $request): LengthAwarePaginator
    {
        $query = CommercialInteraction::query()
            ->with(['salesperson', 'prospect.country', 'customer.country']);

        self::scopeForUser($query, $request->user());

        if ($request->filled('prospectId')) {
            $query->where('prospect_id', $request->integer('prospectId'));
        }

        if ($request->filled('customerId')) {
            $query->where('customer_id', $request->integer('customerId'));
        }

        if ($request->filled('result')) {
            $query->whereIn('result', $request->input('result'));
        }

        if ($request->filled('type')) {
            $query->whereIn('type', $request->input('type'));
        }

        if ($request->filled('dateFrom')) {
            $query->whereDate('occurred_at', '>=', $request->input('dateFrom'));
        }

        if ($request->filled('dateTo')) {
            $query->whereDate('occurred_at', '<=', $request->input('dateTo'));
        }

        return $query
            ->orderByDesc('occurred_at')
            ->paginate(min((int) $request->input('perPage', 10), 100));
    }

    public static function store(array $validated, User $user): array
    {
        if (empty($validated['prospectId']) === empty($validated['customerId'])) {
            throw ValidationException::withMessages([
                'target' => ['Debe indicar exactamente uno entre prospectId y customerId.'],
            ]);
        }

        self::ensureTargetIsAccessible($validated, $user);

        return DB::transaction(function () use ($validated, $user) {
            $salespersonId = $user->hasRole(Role::Comercial->value)
                ? $user->salesperson?->id
                : self::resolveTargetSalespersonId($validated);

            if (! $salespersonId) {
                throw ValidationException::withMessages([
                    'salesperson' => ['No se pudo resolver el comercial de la interacción.'],
                ]);
            }

            $interaction = CommercialInteraction::create([
                'prospect_id' => $validated['prospectId'] ?? null,
                'customer_id' => $validated['customerId'] ?? null,
                'salesperson_id' => $salespersonId,
                'type' => $validated['type'],
                'occurred_at' => $validated['occurredAt'],
                'summary' => $validated['summary'],
                'result' => $validated['result'],
                'next_action_note' => $validated['nextActionNote'] ?? null,
                'next_action_at' => $validated['nextActionAt'] ?? null,
            ]);

            $nextActionAt = $validated['nextActionAt'] ?? null;

            // 1 pending por target: desde una interacción materializamos la agenda en agenda_actions.
            $agenda = [
                'mode' => null,
                'completedAction' => null,
                'createdAction' => null,
            ];

            if (! empty($validated['prospectId'])) {
                $agenda = CrmAgendaService::syncFromInteraction(
                    $user,
                    'prospect',
                    (int) $validated['prospectId'],
                    $interaction->id,
                    $nextActionAt,
                    $validated['nextActionNote'] ?? null,
                    $validated['agendaActionId'] ?? null
                );
            }

            if (! empty($validated['customerId'])) {
                $agenda = CrmAgendaService::syncFromInteraction(
                    $user,
                    'customer',
                    (int) $validated['customerId'],
                    $interaction->id,
                    $nextActionAt,
                    $validated['nextActionNote'] ?? null,
                    $validated['agendaActionId'] ?? null
                );
            }

            if (! empty($validated['prospectId'])) {
                $prospect = Prospect::findOrFail($validated['prospectId']);
                $updates = [
                    'last_contact_at' => $validated['occurredAt'],
                    'next_action_at' => $nextActionAt,
                    'next_action_note' => $nextActionAt !== null ? ($validated['nextActionNote'] ?? null) : null,
                ];
                if ($prospect->status === Prospect::STATUS_NEW) {
                    $updates['status'] = Prospect::STATUS_FOLLOWING;
                }
                $prospect->update($updates);
            }

            return [
                'interaction' => $interaction->load(['salesperson', 'prospect.country', 'customer.country']),
                'agenda' => [
                    'mode' => $agenda['mode'],
                    'completedAction' => $agenda['completedAction']?->toArrayAssoc(),
                    'createdAction' => $agenda['createdAction']?->toArrayAssoc(),
                ],
            ];
        });
    }

    public static function scopeForUser(Builder $query, User $user): void
    {
        if ($user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $query->where('salesperson_id', $user->salesperson->id);
        }
    }

    private static function resolveTargetSalespersonId(array $validated): ?int
    {
        if (! empty($validated['prospectId'])) {
            return Prospect::findOrFail($validated['prospectId'])->salesperson_id;
        }

        if (! empty($validated['customerId'])) {
            return Customer::findOrFail($validated['customerId'])->salesperson_id;
        }

        return null;
    }

    private static function ensureTargetIsAccessible(array $validated, User $user): void
    {
        if (! $user->hasRole(Role::Comercial->value) || ! $user->salesperson) {
            return;
        }

        if (! empty($validated['prospectId'])) {
            $prospect = Prospect::findOrFail($validated['prospectId']);
            if ($prospect->salesperson_id !== $user->salesperson->id) {
                throw ValidationException::withMessages([
                    'prospectId' => ['No puede registrar interacciones sobre prospectos de otro comercial.'],
                ]);
            }
        }

        if (! empty($validated['customerId'])) {
            $customer = Customer::findOrFail($validated['customerId']);
            if ($customer->salesperson_id !== $user->salesperson->id) {
                throw ValidationException::withMessages([
                    'customerId' => ['No puede registrar interacciones sobre clientes de otro comercial.'],
                ]);
            }
        }
    }
}
