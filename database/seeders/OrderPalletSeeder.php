<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderPallet;
use App\Models\Pallet;
use Illuminate\Database\Seeder;

/**
 * Asignación palé–pedido (order_pallets).
 * Solo palés con order_id y pedidos existentes; evita duplicados.
 * Depende de: Orders, Pallets.
 */
class OrderPalletSeeder extends Seeder
{
    public function run(): void
    {
        $palletsWithOrder = Pallet::whereNotNull('order_id')->get();
        if ($palletsWithOrder->isEmpty()) {
            return;
        }

        foreach ($palletsWithOrder as $pallet) {
            OrderPallet::firstOrCreate(
                [
                    'order_id' => $pallet->order_id,
                    'pallet_id' => $pallet->id,
                ]
            );
        }
    }
}
