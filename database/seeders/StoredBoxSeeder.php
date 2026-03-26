<?php

namespace Database\Seeders;

use App\Models\Box;
use App\Models\Store;
use App\Models\StoredBox;
use Illuminate\Database\Seeder;

class StoredBoxSeeder extends Seeder
{
    public function run(): void
    {
        $stores = Store::query()->where('store_type', 'interno')->get();
        if ($stores->isEmpty()) {
            $stores = Store::all();
        }

        if ($stores->isEmpty()) {
            $this->command?->warn('StoredBoxSeeder: no hay almacenes disponibles.');

            return;
        }

        $boxes = Box::query()
            ->whereDoesntHave('storedBox')
            ->limit(20)
            ->get();

        if ($boxes->isEmpty()) {
            return;
        }

        $position = 1;

        foreach ($boxes as $box) {
            $store = $stores->random();

            StoredBox::firstOrCreate(
                ['box_id' => $box->id],
                [
                    'store_id' => $store->id,
                    'position' => $position++,
                ]
            );
        }
    }
}
