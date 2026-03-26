<?php

namespace Database\Seeders;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PalletBox;
use Illuminate\Database\Seeder;

class PalletBoxSeeder extends Seeder
{
    public function run(): void
    {
        $pallets = Pallet::query()
            ->with(['reception.products'])
            ->whereDoesntHave('boxes')
            ->get();

        if ($pallets->isEmpty()) {
            return;
        }

        foreach ($pallets as $pallet) {
            $candidateBoxes = Box::query()
                ->whereDoesntHave('palletBox')
                ->when(
                    $pallet->reception_id && $pallet->reception?->products?->isNotEmpty(),
                    fn ($query) => $query->whereIn('article_id', $pallet->reception->products->pluck('product_id')->all())
                )
                ->inRandomOrder()
                ->take(rand(1, 4))
                ->get();

            foreach ($candidateBoxes as $box) {
                PalletBox::firstOrCreate([
                    'pallet_id' => $pallet->id,
                    'box_id' => $box->id,
                ]);
            }
        }
    }
}
