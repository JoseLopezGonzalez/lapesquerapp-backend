<?php

namespace App\Services\v2;

use App\Models\RouteTemplate;
use Illuminate\Support\Facades\DB;

class RouteTemplateWriteService
{
    public static function store(array $validated, int $userId): RouteTemplate
    {
        return DB::transaction(function () use ($validated, $userId) {
            $template = RouteTemplate::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'salesperson_id' => $validated['salespersonId'] ?? null,
                'field_operator_id' => $validated['fieldOperatorId'] ?? null,
                'created_by_user_id' => $userId,
                'is_active' => $validated['isActive'] ?? true,
            ]);

            self::syncStops($template, $validated['stops'] ?? []);

            return $template->fresh(['salesperson', 'fieldOperator', 'stops']);
        });
    }

    public static function update(RouteTemplate $template, array $validated): RouteTemplate
    {
        return DB::transaction(function () use ($template, $validated) {
            $template->update([
                'name' => $validated['name'] ?? $template->name,
                'description' => array_key_exists('description', $validated) ? $validated['description'] : $template->description,
                'salesperson_id' => array_key_exists('salespersonId', $validated) ? $validated['salespersonId'] : $template->salesperson_id,
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
}
