<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderPallet;
use App\Models\Pallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderPallet>
 */
class OrderPalletFactory extends Factory
{
    protected $model = OrderPallet::class;

    public function definition(): array
    {
        return [
            'order_id'  => Order::query()->value('id') ?? Order::factory(),
            'pallet_id' => Pallet::query()->value('id') ?? Pallet::factory(),
        ];
    }
}
