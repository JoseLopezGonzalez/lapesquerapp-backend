<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderPlannedProductDetail;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Líneas de pedido (order_planned_product_details).
 * Depende de: Orders, Products, Taxes.
 */
class OrderPlannedProductDetailSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('es_ES');

        $orders = Order::all();
        $products = Product::all();
        $tax = Tax::first();

        if ($orders->isEmpty() || $products->isEmpty()) {
            $this->command->warn('OrderPlannedProductDetailSeeder: Ejecuta antes OrderSeeder y ProductSeeder.');
            return;
        }

        foreach ($orders as $order) {
            $numLines = $faker->numberBetween(1, 4);
            $usedProducts = $products->random(min($numLines, $products->count()));

            foreach ($usedProducts as $product) {
                OrderPlannedProductDetail::firstOrCreate(
                    [
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'tax_id' => $tax?->id,
                        'quantity' => $faker->randomFloat(3, 0.1, 500),
                        'boxes' => $faker->numberBetween(1, 50),
                        'unit_price' => $faker->randomFloat(2, 2, 25),
                    ]
                );
            }
        }
    }
}
