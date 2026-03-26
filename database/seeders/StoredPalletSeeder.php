<?php

namespace Database\Seeders;

use App\Models\Pallet;
use App\Models\StoredPallet;
use App\Models\Store;
use Illuminate\Database\Seeder;

class StoredPalletSeeder extends Seeder
{
    public function run(): void
    {
        $stores = Store::query()->where('store_type', 'interno')->get();
        if ($stores->isEmpty()) {
            $stores = Store::all();
        }

        if ($stores->isEmpty()) {
            $this->command?->warn('StoredPalletSeeder: no hay almacenes disponibles.');

            return;
        }

        $pallets = Pallet::query()
            ->where('status', Pallet::STATE_STORED)
            ->whereDoesntHave('storedPallet')
            ->get();

        if ($pallets->isEmpty()) {
            return;
        }

        $position = 1;

        foreach ($pallets as $pallet) {
            $store = $stores->random();

            StoredPallet::firstOrCreate(
                ['pallet_id' => $pallet->id],
                [
                    'store_id' => $store->id,
                    'position' => $position++,
                ]
            );
        }
    }
}
