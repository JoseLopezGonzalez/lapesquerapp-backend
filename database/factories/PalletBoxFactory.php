<?php

namespace Database\Factories;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PalletBox;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PalletBox>
 */
class PalletBoxFactory extends Factory
{
    protected $model = PalletBox::class;

    public function definition(): array
    {
        $box    = Box::query()->first() ?? Box::factory()->create();
        $pallet = Pallet::query()->first() ?? Pallet::factory()->create();

        return [
            'box_id'     => $box->id,
            'pallet_id'  => $pallet->id,
            'lot'        => $box->lot,
            'net_weight' => $box->net_weight,
            'article_id' => $box->article_id,
        ];
    }
}
