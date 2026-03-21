<?php

namespace App\Services\v2;

use App\Models\DeliveryRoute;
use App\Models\RouteStop;
use Illuminate\Support\Facades\DB;

class DeliveryRouteWriteService
{
    public static function store(array $validated, int $userId): DeliveryRoute
    {
        return DB::transaction(function () use ($validated, $userId) {
            $route = DeliveryRoute::create([
                'route_template_id' => $validated['routeTemplateId'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'route_date' => $validated['routeDate'] ?? null,
                'status' => $validated['status'] ?? DeliveryRoute::STATUS_PLANNED,
                'salesperson_id' => $validated['salespersonId'] ?? null,
                'field_operator_id' => $validated['fieldOperatorId'] ?? null,
                'created_by_user_id' => $userId,
            ]);

            self::syncStops($route, $validated);

            return $route->fresh(['salesperson', 'fieldOperator', 'stops']);
        });
    }

    public static function update(DeliveryRoute $route, array $validated): DeliveryRoute
    {
        return DB::transaction(function () use ($route, $validated) {
            $route->update([
                'route_template_id' => array_key_exists('routeTemplateId', $validated) ? $validated['routeTemplateId'] : $route->route_template_id,
                'name' => $validated['name'] ?? $route->name,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : $route->description,
                'route_date' => array_key_exists('routeDate', $validated) ? $validated['routeDate'] : $route->route_date,
                'status' => $validated['status'] ?? $route->status,
                'salesperson_id' => array_key_exists('salespersonId', $validated) ? $validated['salespersonId'] : $route->salesperson_id,
                'field_operator_id' => array_key_exists('fieldOperatorId', $validated) ? $validated['fieldOperatorId'] : $route->field_operator_id,
            ]);

            if (array_key_exists('stops', $validated) || array_key_exists('routeTemplateId', $validated)) {
                self::syncStops($route, $validated);
            }

            return $route->fresh(['salesperson', 'fieldOperator', 'stops']);
        });
    }

    private static function syncStops(DeliveryRoute $route, array $validated): void
    {
        $route->stops()->delete();

        $stops = $validated['stops'] ?? null;
        if ($stops === null && ! empty($validated['routeTemplateId'])) {
            $template = \App\Models\RouteTemplate::with('stops')->find($validated['routeTemplateId']);
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
}
