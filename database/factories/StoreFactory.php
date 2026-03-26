<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Almacén ' . $this->faker->unique()->city(),
            'temperature' => $this->faker->randomFloat(2, -20, 20),
            'capacity' => $this->faker->randomFloat(2, 1000, 100000),
            'map' => '{"posiciones":[{"id":1,"nombre":"U1","x":40,"y":40,"width":460,"height":238,"tipo":"center"}],"elementos":{"fondos":[{"x":0,"y":0,"width":665,"height":1510}],"textos":[]}}',
            'store_type' => 'interno',
            'external_user_id' => null,
        ];
    }
}
