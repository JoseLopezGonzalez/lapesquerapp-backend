<?php

namespace App\Http\Requests\v2;

use App\Models\ExternalUser;
use App\Models\Pallet;
use App\Services\ActorScopeService;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePalletRequest extends FormRequest
{
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
            'orderId' => 'sometimes|nullable|integer|exists:tenant.orders,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $actor = $this->user();
            $scope = app(ActorScopeService::class);

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
}
