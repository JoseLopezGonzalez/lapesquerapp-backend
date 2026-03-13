<?php

namespace App\Http\Requests\v2;

use App\Models\ExternalUser;
use App\Models\Pallet;
use App\Services\ActorScopeService;
use Illuminate\Foundation\Http\FormRequest;

class MoveMultipleToStorePalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'pallet_ids' => 'required|array|min:1',
            'pallet_ids.*' => 'integer|exists:tenant.pallets,id',
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

            if (! $scope->canAccessStoreId($actor, $this->integer('store_id'))) {
                $validator->errors()->add('store_id', 'El almacén destino no pertenece al usuario externo.');
            }

            $invalidPalletId = Pallet::whereIn('id', $this->input('pallet_ids', []))
                ->get()
                ->first(fn ($pallet) => ! $scope->canAccessStoreId($actor, $pallet->store_id));

            if ($invalidPalletId) {
                $validator->errors()->add('pallet_ids', 'Uno o más palets no pertenecen a un almacén del usuario externo.');
            }
        });
    }
}
