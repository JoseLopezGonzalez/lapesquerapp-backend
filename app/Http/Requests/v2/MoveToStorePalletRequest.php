<?php

namespace App\Http\Requests\v2;

use App\Models\ExternalUser;
use App\Models\Pallet;
use App\Services\ActorScopeService;
use Illuminate\Foundation\Http\FormRequest;

class MoveToStorePalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'pallet_id' => 'required|integer|exists:tenant.pallets,id',
            'store_id' => 'required|integer|exists:tenant.stores,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $actor = $this->user();
            if (! $actor instanceof ExternalUser) {
                return;
            }

            $scope = app(ActorScopeService::class);
            $storeId = $this->integer('store_id');
            $pallet = Pallet::find($this->integer('pallet_id'));

            if (! $scope->canAccessStoreId($actor, $storeId)) {
                $validator->errors()->add('store_id', 'El almacén destino no pertenece al usuario externo.');
            }

            if ($pallet && ! $scope->canAccessStoreId($actor, $pallet->store_id)) {
                $validator->errors()->add('pallet_id', 'El palet no pertenece a un almacén del usuario externo.');
            }
        });
    }
}
