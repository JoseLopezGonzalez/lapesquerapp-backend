<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderMaritimeContainer;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderMaritimeContainerFactory extends Factory
{
    protected $model = OrderMaritimeContainer::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::query()->value('id') ?? Order::factory(),
            'container_number' => strtoupper($this->faker->bothify('????#######')),
            'seal_number' => strtoupper($this->faker->bothify('SL######')),
        ];
    }
}
