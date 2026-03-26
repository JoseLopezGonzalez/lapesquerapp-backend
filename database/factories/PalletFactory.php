<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Pallet;
use App\Models\RawMaterialReception;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pallet>
 */
class PalletFactory extends Factory
{
    protected $model = Pallet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'observations' => $this->faker->sentence,
            'status' => $this->faker->randomElement(Pallet::getValidStates()),
            'order_id' => null,
            'reception_id' => null,
            'timeline' => null,
        ];
    }

    public function shipped(): static
    {
        return $this->state(fn () => [
            'status' => Pallet::STATE_SHIPPED,
            'order_id' => Order::query()->value('id') ?? Order::factory(),
            'reception_id' => null,
        ]);
    }

    public function stored(): static
    {
        return $this->state(fn () => [
            'status' => Pallet::STATE_STORED,
            'order_id' => null,
        ]);
    }

    public function fromReception(): static
    {
        return $this->state(fn () => [
            'reception_id' => RawMaterialReception::query()->value('id') ?? RawMaterialReception::factory(),
        ]);
    }
}
