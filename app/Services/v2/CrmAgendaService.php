<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Exceptions\DomainValidationException;
use App\Models\AgendaAction;
use App\Models\CommercialInteraction;
use App\Models\Customer;
use App\Models\Prospect;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmAgendaService
{
    public const TARGET_PROSPECT = 'prospect';

    public const TARGET_CUSTOMER = 'customer';

    /**
     * Devuelve items listos para pintar en calendario (fuente de verdad desde agenda_actions).
     */
    public static function listCalendar(
        Authenticatable $user,
        ?string $startDate,
        ?string $endDate,
        ?string $targetType = null,
        ?array $status = null,
    ): array {
        // Si el front no envía filtros de estado, devolvemos historial completo
        // para poder visualizar reprogramaciones.
        $status = $status ?? ['pending', 'reprogrammed', 'done', 'cancelled'];

        $query = AgendaAction::query()
            ->whereIn('status', $status);

        if ($startDate) {
            $query->whereDate('scheduled_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('scheduled_at', '<=', $endDate);
        }

        if ($targetType) {
            $query->where('target_type', $targetType);
        }

        $query->orderBy('scheduled_at')->orderBy('id');

        $actions = $query->get();

        // Scope por comercial: filtramos por ownership en Prospects/Customers.
        if ($user instanceof User && $user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $actions = self::filterActionsForCommercial($actions, $user->salesperson->id);
        }

        return self::mapActionsToCalendarItems($actions);
    }

    /**
     * Summary compacta para dashboard: overdue / today / next más cercanos.
     */
    public static function summary(
        Authenticatable $user,
        int $limitNext = 10
    ): array {
        $today = Carbon::today(config('app.business_timezone', 'Europe/Madrid'));

        $overdue = self::baseQueryForSummary($user, ['pending'])
            ->whereDate('scheduled_at', '<', $today)
            ->orderByDesc('scheduled_at')
            ->get();

        $todayItems = self::baseQueryForSummary($user, ['pending'])
            ->whereDate('scheduled_at', $today)
            ->orderBy('scheduled_at')
            ->get();

        $next = self::baseQueryForSummary($user, ['pending'])
            ->whereDate('scheduled_at', '>', $today)
            ->orderBy('scheduled_at')
            ->limit($limitNext)
            ->get();

        if ($user instanceof User && $user->hasRole(Role::Comercial->value) && $user->salesperson) {
            $overdue = self::filterActionsForCommercial($overdue, $user->salesperson->id);
            $todayItems = self::filterActionsForCommercial($todayItems, $user->salesperson->id);
            $next = self::filterActionsForCommercial($next, $user->salesperson->id);
        }

        return [
            'overdue' => self::mapActionsToCalendarItems($overdue),
            'today' => self::mapActionsToCalendarItems($todayItems),
            'next' => self::mapActionsToCalendarItems($next),
        ];
    }

    public static function createPendingFromInteraction(
        Authenticatable $user,
        string $targetType,
        int $targetId,
        string $scheduledAt,
        ?string $description,
        ?int $sourceInteractionId = null,
        ?int $previousActionId = null,
    ): AgendaAction {
        self::ensureAllowedTarget($user, $targetType, $targetId);

        $existing = self::getPendingForTarget($targetType, $targetId);
        if ($existing) {
            throw ValidationException::withMessages([
                'nextActionAt' => ['Ya existe una acción pendiente activa para este target. Reprograma o cierra la pendiente actual antes de crear otra.'],
            ]);
        }

        return AgendaAction::create([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'scheduled_at' => $scheduledAt,
            'description' => $description,
            'status' => 'pending',
            'source_interaction_id' => $sourceInteractionId,
            'previous_action_id' => $previousActionId,
        ]);
    }

    /**
     * Sincroniza la agenda desde una interacción.
     *
     * Modos soportados:
     * - created: llega nextActionAt sin agendaActionId
     * - completed: llega agendaActionId sin nextActionAt
     * - completed_and_created: llegan agendaActionId y nextActionAt
     */
    public static function syncFromInteraction(
        Authenticatable $user,
        string $targetType,
        int $targetId,
        int $interactionId,
        ?string $nextActionAt,
        ?string $nextActionNote,
        ?int $agendaActionId
    ): array {
        if ($nextActionAt !== null && $agendaActionId !== null) {
            $completed = self::completeFromInteraction(
                $user,
                $agendaActionId,
                $interactionId,
                $targetType,
                $targetId
            );

            $created = self::createPendingFromInteraction(
                $user,
                $targetType,
                $targetId,
                $nextActionAt,
                $nextActionNote,
                $interactionId,
                $agendaActionId
            );

            return [
                'mode' => 'completed_and_created',
                'completedAction' => $completed,
                'createdAction' => $created,
            ];
        }

        if ($nextActionAt !== null) {
            return [
                'mode' => 'created',
                'completedAction' => null,
                'createdAction' => self::createPendingFromInteraction(
                    $user,
                    $targetType,
                    $targetId,
                    $nextActionAt,
                    $nextActionNote,
                    $interactionId,
                    null
                ),
            ];
        }

        if ($agendaActionId === null) {
            throw ValidationException::withMessages([
                'agendaActionId' => ['Se requiere agendaActionId para marcar como hecha una acción sin próxima acción.'],
            ]);
        }

        return [
            'mode' => 'completed',
            'completedAction' => self::completeFromInteraction(
                $user,
                $agendaActionId,
                $interactionId,
                $targetType,
                $targetId
            ),
            'createdAction' => null,
        ];
    }

    /**
     * Marca como done la acción indicada.
     * Requiere interacción de cierre ligada explícitamente vía agendaActionId.
     */
    public static function completeFromInteraction(
        Authenticatable $user,
        int $agendaActionId,
        int $completedInteractionId,
        ?string $expectedTargetType = null,
        ?int $expectedTargetId = null,
    ): AgendaAction {
        $action = AgendaAction::query()->where('id', $agendaActionId)->first();
        if (! $action) {
            throw ValidationException::withMessages([
                'agendaActionId' => ['La acción de agenda indicada no existe.'],
            ]);
        }

        if ($action->status !== 'pending') {
            throw ValidationException::withMessages([
                'agendaActionId' => ['Solo se puede marcar como hecha una acción pendiente activa.'],
            ]);
        }

        if ($expectedTargetType !== null && $action->target_type !== $expectedTargetType) {
            throw ValidationException::withMessages([
                'agendaActionId' => ['La acción de agenda no corresponde al target de esta interacción.'],
            ]);
        }

        if ($expectedTargetId !== null && (int) $action->target_id !== (int) $expectedTargetId) {
            throw ValidationException::withMessages([
                'agendaActionId' => ['La acción de agenda no corresponde al target de esta interacción.'],
            ]);
        }

        self::ensureAllowedTarget($user, $action->target_type, (int) $action->target_id);

        $action->update([
            'status' => 'done',
            'completed_interaction_id' => $completedInteractionId,
        ]);

        return $action->fresh();
    }

    public static function reschedule(
        Authenticatable $user,
        int $agendaActionId,
        string $newScheduledAt,
        ?string $newDescription,
        ?int $sourceInteractionId = null,
    ): AgendaAction {
        $action = AgendaAction::query()->where('id', $agendaActionId)->first();
        if (! $action) {
            throw ValidationException::withMessages([
                'agendaActionId' => ['La acción de agenda indicada no existe.'],
            ]);
        }

        self::ensureAllowedTarget($user, $action->target_type, (int) $action->target_id);

        if ($action->status !== 'pending') {
            throw ValidationException::withMessages([
                'agendaActionId' => ['Solo se puede reprogramar una acción pendiente activa.'],
            ]);
        }

        $effectiveDescription = $newDescription ?? $action->description;

        return DB::transaction(function () use (
            $action,
            $newScheduledAt,
            $effectiveDescription,
            $sourceInteractionId
        ) {
            // En V1, reprogramar significa que la acción antigua pasa a `reprogrammed`
            // (y la nueva representa el mismo trabajo, pero en otra fecha).
            $action->update(['status' => 'reprogrammed']);

            // Al cancelar, creamos la nueva pendiente con enlace histórico.
            $new = AgendaAction::create([
                'target_type' => $action->target_type,
                'target_id' => $action->target_id,
                'scheduled_at' => $newScheduledAt,
                'description' => $effectiveDescription,
                'status' => 'pending',
                'source_interaction_id' => $sourceInteractionId,
                'previous_action_id' => $action->id,
            ]);

            return $new->fresh();
        });
    }

    public static function cancel(
        Authenticatable $user,
        int $agendaActionId
    ): AgendaAction {
        $action = AgendaAction::query()->where('id', $agendaActionId)->first();
        if (! $action) {
            throw ValidationException::withMessages([
                'agendaActionId' => ['La acción de agenda indicada no existe.'],
            ]);
        }

        self::ensureAllowedTarget($user, $action->target_type, (int) $action->target_id);

        if ($action->status !== 'pending') {
            throw ValidationException::withMessages([
                'agendaActionId' => ['Solo se puede cancelar una acción pendiente activa.'],
            ]);
        }

        $action->update(['status' => 'cancelled']);

        return $action->fresh();
    }

    public static function pendingSnapshot(
        Authenticatable $user,
        string $targetType,
        int $targetId
    ): ?array {
        self::ensureAllowedTarget($user, $targetType, $targetId);

        $pending = self::getPendingForTarget($targetType, $targetId);
        if (! $pending) {
            return null;
        }

        $today = Carbon::today(config('app.business_timezone', 'Europe/Madrid'));
        $scheduledAt = Carbon::parse($pending->scheduled_at?->format('Y-m-d'), config('app.business_timezone', 'Europe/Madrid'));
        $daysOverdue = $scheduledAt->isBefore($today) ? $scheduledAt->diffInDays($today) : 0;

        return array_merge($pending->toArrayAssoc(), [
            'isOverdue' => $daysOverdue > 0,
            'daysOverdue' => $daysOverdue,
        ]);
    }

    public static function resolveNextAction(
        Authenticatable $user,
        string $targetType,
        int $targetId,
        string $strategy,
        ?string $nextActionAt = null,
        ?string $description = null,
        ?string $reason = null,
        ?int $sourceInteractionId = null,
        ?int $expectedPendingId = null
    ): array {
        self::ensureAllowedTarget($user, $targetType, $targetId);

        $connection = DB::connection('tenant');
        $driver = $connection->getDriverName();
        $lockKey = sprintf('crm_next_action:%s:%d', $targetType, $targetId);
        $lockAcquired = false;

        try {
            if ($driver === 'mysql') {
                $lockRow = $connection->selectOne('SELECT GET_LOCK(?, 10) AS l', [$lockKey]);
                $lockAcquired = (int) ($lockRow->l ?? 0) === 1;
                if (! $lockAcquired) {
                    throw new DomainValidationException(
                        'STALE_PENDING',
                        'La próxima acción ha cambiado. Recarga y revisa el estado actual.',
                        ['lock' => ['STALE_PENDING: no se pudo adquirir lock de resolución para este target.']]
                    );
                }
            }

            return $connection->transaction(function () use (
                $targetType,
                $targetId,
                $strategy,
                $nextActionAt,
                $description,
                $reason,
                $sourceInteractionId,
                $expectedPendingId
            ) {
                $pending = AgendaAction::query()
                    ->where('target_type', $targetType)
                    ->where('target_id', $targetId)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if ($expectedPendingId !== null) {
                    if (! $pending || (int) $pending->id !== (int) $expectedPendingId) {
                        throw new DomainValidationException(
                            'STALE_PENDING',
                            'La próxima acción ha cambiado. Recarga y revisa el estado actual.',
                            ['expectedPendingId' => ['STALE_PENDING: la acción pendiente ya no coincide con la vista del usuario.']]
                        );
                    }
                }

                $previousAfter = $pending;
                $current = $pending;
                $changed = false;

                if ($strategy === 'keep') {
                    if (! $pending) {
                        throw new DomainValidationException(
                            'NO_PENDING_TO_UPDATE',
                            'No existe una acción pendiente para mantener.',
                            ['strategy' => ['NO_PENDING_TO_UPDATE: no existe pending activa.']]
                        );
                    }
                }

                if ($strategy === 'reschedule') {
                    if (! $pending) {
                        throw new DomainValidationException(
                            'NO_PENDING_TO_UPDATE',
                            'No existe una acción pendiente para reprogramar.',
                            ['strategy' => ['NO_PENDING_TO_UPDATE: no existe pending activa.']]
                        );
                    }

                    $pending->update(['status' => 'reprogrammed']);
                    $previousAfter = $pending->fresh();

                    $new = AgendaAction::create([
                        'target_type' => $targetType,
                        'target_id' => $targetId,
                        'scheduled_at' => $nextActionAt,
                        'description' => $pending->description,
                        'status' => 'pending',
                        'source_interaction_id' => $sourceInteractionId,
                        'previous_action_id' => $pending->id,
                    ]);
                    $current = $new->fresh();
                    $changed = true;
                }

                if ($strategy === 'reschedule_with_description') {
                    if (! $pending) {
                        throw new DomainValidationException(
                            'NO_PENDING_TO_UPDATE',
                            'No existe una acción pendiente para reprogramar.',
                            ['strategy' => ['NO_PENDING_TO_UPDATE: no existe pending activa.']]
                        );
                    }

                    $pending->update(['status' => 'reprogrammed']);
                    $previousAfter = $pending->fresh();

                    $new = AgendaAction::create([
                        'target_type' => $targetType,
                        'target_id' => $targetId,
                        'scheduled_at' => $nextActionAt,
                        'description' => $description,
                        'status' => 'pending',
                        'source_interaction_id' => $sourceInteractionId,
                        'previous_action_id' => $pending->id,
                    ]);
                    $current = $new->fresh();
                    $changed = true;
                }

                if ($strategy === 'override') {
                    if (! $pending) {
                        throw new DomainValidationException(
                            'NO_PENDING_TO_UPDATE',
                            'No existe una acción pendiente para sobreescribir.',
                            ['strategy' => ['NO_PENDING_TO_UPDATE: no existe pending activa.']]
                        );
                    }

                    $pending->update([
                        'status' => 'cancelled',
                        'reason' => $reason,
                    ]);
                    $previousAfter = $pending->fresh();

                    $new = AgendaAction::create([
                        'target_type' => $targetType,
                        'target_id' => $targetId,
                        'scheduled_at' => $nextActionAt,
                        'description' => $description,
                        'status' => 'pending',
                        'reason' => null,
                        'source_interaction_id' => $sourceInteractionId,
                        'previous_action_id' => $pending->id,
                    ]);
                    $current = $new->fresh();
                    $changed = true;
                }

                if ($strategy === 'create_if_none') {
                    if ($pending) {
                        throw new DomainValidationException(
                            'PENDING_EXISTS',
                            'Ya existe una acción pendiente activa para este target.',
                            ['strategy' => ['PENDING_EXISTS: ya existe pending activa.']]
                        );
                    }

                    $new = AgendaAction::create([
                        'target_type' => $targetType,
                        'target_id' => $targetId,
                        'scheduled_at' => $nextActionAt,
                        'description' => $description,
                        'status' => 'pending',
                        'reason' => null,
                        'source_interaction_id' => $sourceInteractionId,
                        'previous_action_id' => null,
                    ]);
                    $current = $new->fresh();
                    $previousAfter = null;
                    $changed = true;
                }

                self::syncLegacyProspectNextAction($targetType, $targetId, $current);

                return [
                    'strategy' => $strategy,
                    'changed' => $changed,
                    'previousPending' => $previousAfter?->toArrayAssoc(),
                    'currentPending' => $current?->toArrayAssoc(),
                ];
            });
        } finally {
            if ($driver === 'mysql' && $lockAcquired) {
                $connection->select('SELECT RELEASE_LOCK(?)', [$lockKey]);
            }
        }
    }

    public static function getPendingForTarget(string $targetType, int $targetId): ?AgendaAction
    {
        return AgendaAction::query()
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->where('status', 'pending')
            ->first();
    }

    private static function syncLegacyProspectNextAction(string $targetType, int $targetId, ?AgendaAction $currentPending): void
    {
        if ($targetType !== self::TARGET_PROSPECT) {
            return;
        }

        $prospect = Prospect::query()->find($targetId);
        if (! $prospect) {
            return;
        }

        if ($currentPending) {
            $prospect->update([
                'next_action_at' => $currentPending->scheduled_at?->format('Y-m-d'),
                'next_action_note' => $currentPending->description,
            ]);

            return;
        }

        $prospect->update([
            'next_action_at' => null,
            'next_action_note' => null,
        ]);
    }

    /**
     * Backfill inicial desde el “legacy”:
     * - Prospect: prospects.next_action_at + next_action_note
     * - Customer: última interacción por fecha de ocurrencia; si esa última tiene next_action_at,
     *   se materializa como pending en agenda_actions.
     *
     * Nota: se ejecuta en contexto de la conexión tenant (command en cada tenant).
     */
    public static function backfillFromLegacy(): array
    {
        $created = 0;
        $skipped = 0;

        // 1) Prospects: la agenda pendiente principal ya vive en prospects.next_action_*
        $prospects = Prospect::query()
            ->whereNotNull('next_action_at')
            ->get(['id', 'next_action_at', 'next_action_note']);

        foreach ($prospects as $prospect) {
            if (self::getPendingForTarget(self::TARGET_PROSPECT, (int) $prospect->id)) {
                $skipped++;

                continue;
            }

            AgendaAction::create([
                'target_type' => self::TARGET_PROSPECT,
                'target_id' => (int) $prospect->id,
                'scheduled_at' => $prospect->next_action_at->format('Y-m-d'),
                'description' => $prospect->next_action_note ?? '',
                'status' => 'pending',
                'source_interaction_id' => null,
            ]);
            $created++;
        }

        // 2) Customers: “última interacción” por occurred_at (aunque no tenga nextActionAt).
        $latestByCustomer = CommercialInteraction::query()
            ->whereNotNull('customer_id')
            ->orderByDesc('occurred_at')
            ->get(['customer_id', 'next_action_at', 'next_action_note', 'occurred_at', 'id'])
            ->unique('customer_id');

        foreach ($latestByCustomer as $interaction) {
            $customerId = (int) $interaction->customer_id;

            if (self::getPendingForTarget(self::TARGET_CUSTOMER, $customerId)) {
                $skipped++;

                continue;
            }

            if ($interaction->next_action_at === null) {
                // Si la última interacción no dejó próxima acción, no creamos pending.
                $skipped++;

                continue;
            }

            AgendaAction::create([
                'target_type' => self::TARGET_CUSTOMER,
                'target_id' => $customerId,
                'scheduled_at' => Carbon::parse($interaction->next_action_at)->format('Y-m-d'),
                'description' => $interaction->next_action_note ?? '',
                'status' => 'pending',
                'source_interaction_id' => (int) $interaction->id,
            ]);
            $created++;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    private static function ensureAllowedTarget(Authenticatable $user, string $targetType, int $targetId): void
    {
        if (! ($user instanceof User)) {
            return;
        }

        if (! $user->hasRole(Role::Comercial->value) || ! $user->salesperson) {
            return;
        }

        if ($targetType === self::TARGET_PROSPECT) {
            $prospect = Prospect::query()->select('id', 'salesperson_id')->find($targetId);
            if (! $prospect || (int) $prospect->salesperson_id !== (int) $user->salesperson->id) {
                throw ValidationException::withMessages([
                    'prospectId' => ['No puede operar con un prospecto ajeno.'],
                ]);
            }
        }

        if ($targetType === self::TARGET_CUSTOMER) {
            $customer = Customer::query()->select('id', 'salesperson_id')->find($targetId);
            if (! $customer || (int) $customer->salesperson_id !== (int) $user->salesperson->id) {
                throw ValidationException::withMessages([
                    'customerId' => ['No puede operar con un cliente ajeno.'],
                ]);
            }
        }
    }

    private static function baseQueryForSummary(Authenticatable $user, array $status): \Illuminate\Database\Eloquent\Builder
    {
        $query = AgendaAction::query()->whereIn('status', $status);

        // Scope por comercial para no mezclar datos.
        if ($user instanceof User && $user->hasRole(Role::Comercial->value) && $user->salesperson) {
            // Filtrado tardío: lo aplicamos tras cargar (batch), para mantenerlo simple y correcto.
            // (El dataset por summary es pequeño: hoy + próximos).
        }

        return $query;
    }

    private static function filterActionsForCommercial(EloquentCollection $actions, int $salespersonId): EloquentCollection
    {
        $prospectIds = $actions
            ->where('target_type', self::TARGET_PROSPECT)
            ->pluck('target_id')
            ->values()
            ->all();

        $customerIds = $actions
            ->where('target_type', self::TARGET_CUSTOMER)
            ->pluck('target_id')
            ->values()
            ->all();

        $allowedProspectIds = Prospect::query()
            ->whereIn('id', $prospectIds)
            ->where('salesperson_id', $salespersonId)
            ->pluck('id')
            ->values()
            ->all();

        $allowedCustomerIds = Customer::query()
            ->whereIn('id', $customerIds)
            ->where('salesperson_id', $salespersonId)
            ->pluck('id')
            ->values()
            ->all();

        return $actions->filter(function (AgendaAction $action) use ($allowedProspectIds, $allowedCustomerIds) {
            if ($action->target_type === self::TARGET_PROSPECT) {
                return in_array((int) $action->target_id, $allowedProspectIds, true);
            }
            if ($action->target_type === self::TARGET_CUSTOMER) {
                return in_array((int) $action->target_id, $allowedCustomerIds, true);
            }

            return false;
        })->values();
    }

    private static function mapActionsToCalendarItems(EloquentCollection $actions): array
    {
        $prospectIds = $actions->where('target_type', self::TARGET_PROSPECT)->pluck('target_id')->values()->all();
        $customerIds = $actions->where('target_type', self::TARGET_CUSTOMER)->pluck('target_id')->values()->all();

        $prospectLabels = Prospect::query()
            ->whereIn('id', $prospectIds)
            ->pluck('company_name', 'id')
            ->all();

        $customerLabels = Customer::query()
            ->whereIn('id', $customerIds)
            ->pluck('name', 'id')
            ->all();

        return $actions->map(function (AgendaAction $action) use ($prospectLabels, $customerLabels) {
            $label = $action->target_type === self::TARGET_PROSPECT
                ? ($prospectLabels[$action->target_id] ?? null)
                : ($customerLabels[$action->target_id] ?? null);

            return [
                'agendaActionId' => $action->id,
                'scheduledAt' => $action->scheduled_at?->format('Y-m-d'),
                'description' => $action->description,
                'status' => $action->status,
                'target' => [
                    'type' => $action->target_type,
                    'id' => $action->target_id,
                ],
                'label' => $label,
            ];
        })->values()->all();
    }
}
