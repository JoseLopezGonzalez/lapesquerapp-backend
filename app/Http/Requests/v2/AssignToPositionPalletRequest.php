<?php

namespace App\Http\Requests\v2;

use App\Models\ExternalUser;
use App\Models\Pallet;
use App\Services\ActorScopeService;
use Illuminate\Foundation\Http\FormRequest;

class AssignToPositionPalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pallet::class);
    }

    public function rules(): array
    {
        return [
            'position_id' => 'required|integer|min:1',
            'pallet_ids' => 'required|array|min:1',
            'pallet_ids.*' => 'integer|exists:tenant.pallets,id',
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
            $invalidPallet = Pallet::whereIn('id', $this->input('pallet_ids', []))
                ->get()
                ->first(fn ($pallet) => ! $scope->canAccessStoreId($actor, $pallet->store_id));

            if ($invalidPallet) {
                $validator->errors()->add('pallet_ids', 'Uno o más palets no pertenecen a un almacén del usuario externo.');
            }
        });
    }
}
