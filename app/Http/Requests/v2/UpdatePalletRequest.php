<?php

namespace App\Http\Requests\v2;

use App\Models\ExternalUser;
use App\Models\Pallet;
use App\Services\ActorScopeService;
use App\Support\PalletManualCostPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePalletRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (PalletManualCostPolicy::authorized($this->user())) {
            return;
        }

        $boxes = $this->input('boxes');
        if (! is_array($boxes)) {
            return;
        }

        $boxes = array_map(static function ($box) {
            if (! is_array($box)) {
                return $box;
            }
            unset($box['manualCostPerKg']);

            return $box;
        }, $boxes);

        $this->merge(['boxes' => $boxes]);
    }

    public function authorize(): bool
    {
        $id = $this->route('pallet');
        if ($id instanceof Pallet) {
            return $this->user()->can('update', $id);
        }
        if (! $id) {
            return false;
        }

        return $this->user()->can('update', Pallet::findOrFail($id));
    }

    public function rules(): array
    {
        return [
            'id' => 'required|integer',
            'observations' => 'sometimes|nullable|string|max:1000',
            'store.id' => 'sometimes|nullable|integer|exists:tenant.stores,id',
            'state.id' => 'sometimes|integer|in:1,2,3,4',
            'boxes' => 'sometimes|array',
            'boxes.*.id' => 'sometimes|nullable|integer',
            'boxes.*.product.id' => 'required_with:boxes|integer|exists:tenant.products,id',
            'boxes.*.lot' => 'required_with:boxes|string|max:255',
            'boxes.*.gs1128' => 'required_with:boxes|string|max:255',
            'boxes.*.grossWeight' => 'required_with:boxes|numeric|min:0',
            'boxes.*.netWeight' => 'required_with:boxes|numeric|min:0.01',
            'boxes.*.manualCostPerKg' => 'nullable|numeric|min:0',
            'orderId' => 'sometimes|nullable|integer|exists:tenant.orders,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $actor = $this->user();
            $scope = app(ActorScopeService::class);

            if ($this->hasManualCostPerKg() && ! $this->userCanEditManualCost()) {
                $validator->errors()->add(
                    'boxes',
                    'Solo usuarios administradores o técnicos pueden modificar el coste manual por kg de las cajas.'
                );
            }

            if (! $actor instanceof ExternalUser) {
                return;
            }

            if ($this->filled('orderId')) {
                $validator->errors()->add('orderId', 'Un usuario externo no puede vincular palets a pedidos.');
            }

            $storeId = data_get($this->validated(), 'store.id');
            if ($storeId !== null && ! $scope->canAccessStoreId($actor, (int) $storeId)) {
                $validator->errors()->add('store.id', 'El almacén seleccionado no pertenece al usuario externo.');
            }
        });
    }

    private function hasManualCostPerKg(): bool
    {
        foreach ($this->input('boxes', []) as $box) {
            if (is_array($box) && array_key_exists('manualCostPerKg', $box)) {
                return true;
            }
        }

        return false;
    }

    private function userCanEditManualCost(): bool
    {
        return PalletManualCostPolicy::authorized($this->user());
    }
}
