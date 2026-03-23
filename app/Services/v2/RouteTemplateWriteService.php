<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Models\RouteTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RouteTemplateWriteService
{
    public static function store(array $validated, User $user): RouteTemplate
    {
        return DB::transaction(function () use ($validated, $user) {
            $template = RouteTemplate::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'salesperson_id' => self::resolveSalespersonId($validated, $user),
                'field_operator_id' => $validated['fieldOperatorId'] ?? null,
                'created_by_user_id' => $user->id,
                'is_active' => $validated['isActive'] ?? true,
            ]);

            self::syncStops($template, $validated['stops'] ?? []);

            return $template->fresh(['salesperson', 'fieldOperator', 'stops']);
        });
    }

    public static function update(RouteTemplate $template, array $validated, User $user): RouteTemplate
    {
        return DB::transaction(function () use ($template, $validated, $user) {
            $salespersonId = array_key_exists('salespersonId', $validated)
                ? self::resolveSalespersonId($validated, $user)
                : $template->salesperson_id;

            $template->update([
                'name' => $validated['name'] ?? $template->name,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : $template->description,
                'salesperson_id' => $salespersonId,
                'field_operator_id' => array_key_exists('fieldOperatorId', $validated) ? $validated['fieldOperatorId'] : $template->field_operator_id,
                'is_active' => $validated['isActive'] ?? $template->is_active,
            ]);

            if (array_key_exists('stops', $validated)) {
                self::syncStops($template, $validated['stops'] ?? []);
            }

            return $template->fresh(['salesperson', 'fieldOperator', 'stops']);
        });
    }

    private static function syncStops(RouteTemplate $template, array $stops): void
    {
        $template->stops()->delete();

        foreach ($stops as $stop) {
            $template->stops()->create([
                'position' => $stop['position'],
                'stop_type' => $stop['stopType'],
                'target_type' => $stop['targetType'] ?? null,
                'customer_id' => $stop['customerId'] ?? null,
                'prospect_id' => $stop['prospectId'] ?? null,
                'label' => $stop['label'] ?? null,
                'address' => $stop['address'] ?? null,
                'notes' => $stop['notes'] ?? null,
            ]);
        }
    }

    private static function resolveSalespersonId(array $validated, User $user): ?int
    {
        if (! $user->hasRole(Role::Comercial->value)) {
            return $validated['salespersonId'] ?? null;
        }

        if (! $user->salesperson) {
            throw ValidationException::withMessages([
                'salespersonId' => ['El usuario comercial no tiene comercial asociado para gestionar plantillas de ruta.'],
            ]);
        }

        if (array_key_exists('salespersonId', $validated)
            && $validated['salespersonId'] !== null
            && (int) $validated['salespersonId'] !== $user->salesperson->id) {
            throw ValidationException::withMessages([
                'salespersonId' => ['No puede asignar plantillas de ruta a otro comercial.'],
            ]);
        }

        return $user->salesperson->id;
    }
}
