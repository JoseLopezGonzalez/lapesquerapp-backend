<?php

namespace App\Services\v2;

use App\Enums\Role;
use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use App\Models\RouteTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryRouteWriteService
{
    public static function store(array $validated, User $user): DeliveryRoute
    {
        return DB::transaction(function () use ($validated, $user) {
            $salespersonId = self::resolveSalespersonId($validated, $user);

            $route = DeliveryRoute::create([
                'route_template_id' => $validated['routeTemplateId'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'route_date' => $validated['routeDate'] ?? null,
                'status' => $validated['status'] ?? DeliveryRoute::STATUS_PLANNED,
                'salesperson_id' => $salespersonId,
                'field_operator_id' => $validated['fieldOperatorId'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            self::syncStops($route, $validated, $user);

            return $route->fresh(['salesperson', 'fieldOperator', 'stops']);
        });
    }

    public static function update(DeliveryRoute $route, array $validated, User $user): DeliveryRoute
    {
        return DB::transaction(function () use ($route, $validated, $user) {
            $salespersonId = array_key_exists('salespersonId', $validated)
                ? self::resolveSalespersonId($validated, $user)
                : $route->salesperson_id;

            $route->update([
                'route_template_id' => array_key_exists('routeTemplateId', $validated) ? $validated['routeTemplateId'] : $route->route_template_id,
                'name' => $validated['name'] ?? $route->name,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : $route->description,
                'route_date' => array_key_exists('routeDate', $validated) ? $validated['routeDate'] : $route->route_date,
                'status' => $validated['status'] ?? $route->status,
                'salesperson_id' => $salespersonId,
                'field_operator_id' => array_key_exists('fieldOperatorId', $validated) ? $validated['fieldOperatorId'] : $route->field_operator_id,
            ]);

            if (array_key_exists('stops', $validated) || array_key_exists('routeTemplateId', $validated)) {
                self::syncStops($route, $validated, $user);
            }

            return $route->fresh(['salesperson', 'fieldOperator', 'stops']);
        });
    }

    private static function syncStops(DeliveryRoute $route, array $validated, User $user): void
    {
        $route->stops()->delete();

        $stops = $validated['stops'] ?? null;
        if ($stops === null && ! empty($validated['routeTemplateId'])) {
            $template = RouteTemplate::with('stops')->find($validated['routeTemplateId']);
            self::ensureTemplateIsAccessible($template, $user);
            $stops = $template?->stops->map(fn ($stop) => [
                'position' => $stop->position,
                'stopType' => $stop->stop_type,
                'targetType' => $stop->target_type,
                'customerId' => $stop->customer_id,
                'prospectId' => $stop->prospect_id,
                'label' => $stop->label,
                'address' => $stop->address,
                'notes' => $stop->notes,
                'templateStopId' => $stop->id,
            ])->all() ?? [];
        }

        foreach ($stops ?? [] as $stop) {
            $route->stops()->create([
                'route_template_stop_id' => $stop['templateStopId'] ?? null,
                'position' => $stop['position'],
                'stop_type' => $stop['stopType'],
                'target_type' => $stop['targetType'] ?? null,
                'customer_id' => $stop['customerId'] ?? null,
                'prospect_id' => $stop['prospectId'] ?? null,
                'label' => $stop['label'] ?? null,
                'address' => $stop['address'] ?? null,
                'notes' => $stop['notes'] ?? null,
                'status' => RouteStop::STATUS_PENDING,
                'result_type' => null,
                'result_notes' => null,
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
                'salespersonId' => ['El usuario comercial no tiene comercial asociado para gestionar rutas.'],
            ]);
        }

        if (array_key_exists('salespersonId', $validated)
            && $validated['salespersonId'] !== null
            && (int) $validated['salespersonId'] !== $user->salesperson->id) {
            throw ValidationException::withMessages([
                'salespersonId' => ['No puede asignar rutas a otro comercial.'],
            ]);
        }

        return $user->salesperson->id;
    }

    private static function ensureTemplateIsAccessible(?RouteTemplate $template, User $user): void
    {
        if (! $template || ! $user->hasRole(Role::Comercial->value)) {
            return;
        }

        if (! $user->salesperson) {
            throw ValidationException::withMessages([
                'routeTemplateId' => ['El usuario comercial no tiene comercial asociado para usar plantillas.'],
            ]);
        }

        $ownsTemplate = $template->salesperson_id === $user->salesperson->id
            || ($template->salesperson_id === null && $template->created_by_user_id === $user->id);

        if (! $ownsTemplate) {
            throw ValidationException::withMessages([
                'routeTemplateId' => ['No puede usar plantillas de ruta de otro comercial.'],
            ]);
        }
    }
}
