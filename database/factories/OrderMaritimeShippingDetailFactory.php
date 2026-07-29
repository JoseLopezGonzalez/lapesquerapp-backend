<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderMaritimeShippingDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderMaritimeShippingDetailFactory extends Factory
{
    protected $model = OrderMaritimeShippingDetail::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::query()->value('id') ?? Order::factory(),
            'vessel_name' => $this->faker->words(2, true),
            'voyage_number' => 'V-'.$this->faker->unique()->numerify('####'),
            'export_invoice_number' => 'EXP-'.$this->faker->unique()->numerify('####'),
            'swb_number' => 'SWB-'.$this->faker->unique()->numerify('######'),
            'loading_port' => $this->faker->city(),
            'discharge_port' => $this->faker->city(),
        ];
    }
}
