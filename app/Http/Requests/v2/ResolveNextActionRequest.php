<?php

namespace App\Http\Requests\v2;

use Illuminate\Foundation\Http\FormRequest;

class ResolveNextActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'targetType' => 'required|string|in:prospect,customer',
            'targetId' => 'required|integer',
            'strategy' => 'required|string|in:keep,reschedule,reschedule_with_description,override,create_if_none',
            'nextActionAt' => 'nullable|date',
            'description' => 'nullable|string|max:255',
            'reason' => 'nullable|string|max:1000',
            'sourceInteractionId' => 'nullable|integer|exists:tenant.commercial_interactions,id',
            'expectedPendingId' => 'nullable|integer',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function () use ($validator) {
            $strategy = $this->input('strategy');

            if (! $strategy) {
                return;
            }

            $has = fn (string $field): bool => $this->filled($field);

            if ($strategy === 'keep') {
                foreach (['nextActionAt', 'description', 'reason', 'sourceInteractionId'] as $field) {
                    if ($has($field)) {
                        $validator->errors()->add($field, 'INVALID_STRATEGY_FIELDS: keep no permite '.$field.'.');
                    }
                }
            }

            if ($strategy === 'reschedule') {
                if (! $has('nextActionAt')) {
                    $validator->errors()->add('nextActionAt', 'INVALID_STRATEGY_FIELDS: reschedule requiere nextActionAt.');
                }
                if ($has('description')) {
                    $validator->errors()->add('description', 'INVALID_STRATEGY_FIELDS: reschedule no permite description.');
                }
                if ($has('reason')) {
                    $validator->errors()->add('reason', 'INVALID_STRATEGY_FIELDS: reschedule no permite reason.');
                }
            }

            if ($strategy === 'reschedule_with_description') {
                if (! $has('nextActionAt')) {
                    $validator->errors()->add('nextActionAt', 'INVALID_STRATEGY_FIELDS: reschedule_with_description requiere nextActionAt.');
                }
                if (! $has('description')) {
                    $validator->errors()->add('description', 'INVALID_STRATEGY_FIELDS: reschedule_with_description requiere description.');
                }
                if ($has('reason')) {
                    $validator->errors()->add('reason', 'INVALID_STRATEGY_FIELDS: reschedule_with_description no permite reason.');
                }
            }

            if ($strategy === 'override') {
                if (! $has('nextActionAt')) {
                    $validator->errors()->add('nextActionAt', 'INVALID_STRATEGY_FIELDS: override requiere nextActionAt.');
                }
                if (! $has('reason')) {
                    $validator->errors()->add('reason', 'INVALID_STRATEGY_FIELDS: override requiere reason.');
                }
            }

            if ($strategy === 'create_if_none') {
                if (! $has('nextActionAt')) {
                    $validator->errors()->add('nextActionAt', 'INVALID_STRATEGY_FIELDS: create_if_none requiere nextActionAt.');
                }
                if ($has('reason')) {
                    $validator->errors()->add('reason', 'INVALID_STRATEGY_FIELDS: create_if_none no permite reason.');
                }
            }
        });
    }
}

