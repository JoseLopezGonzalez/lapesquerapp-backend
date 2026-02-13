<?php

namespace Database\Seeders;

use App\Models\Pallet;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Palés de desarrollo (estados 1=registered, 2=stored, 3=shipped, 4=processed).
 * Algunos vinculados a pedidos para flujo realista.
 * Depende opcionalmente de: Orders.
 */
class PalletSeeder extends Seeder
{
    public function run(): void
    {
        if (Pallet::count() >= 20) {
            $this->command->info('PalletSeeder: Ya existen suficientes palés. Omitiendo creación.');
            return;
        }

        $faker = Faker::create('es_ES');

        $orders = Order::all();
        $statuses = [
            Pallet::STATE_REGISTERED,
            Pallet::STATE_STORED,
            Pallet::STATE_SHIPPED,
            Pallet::STATE_PROCESSED,
        ];

        $toCreate = 20 - Pallet::count();
        if ($toCreate <= 0) {
            return;
        }

        for ($i = 0; $i < $toCreate; $i++) {
            $status = $faker->randomElement($statuses);
            $orderId = null;
            if ($orders->isNotEmpty() && in_array($status, [Pallet::STATE_SHIPPED], true)) {
                $orderId = $faker->optional(0.6)->randomElement($orders->pluck('id')->toArray());
            }

            Pallet::create([
                'observations' => $faker->optional(0.3)->sentence(),
                'status' => $status,
                'order_id' => $orderId,
            ]);
        }
    }
}
