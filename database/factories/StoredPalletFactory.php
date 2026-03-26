<?php

namespace Database\Factories;

use App\Models\Pallet;
use App\Models\Store;
use App\Models\StoredPallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StoredPallet>
 */
class StoredPalletFactory extends Factory
{
    protected $model = StoredPallet::class;

    public function definition(): array
    {
        return [
            'pallet_id' => Pallet::query()->where('status', Pallet::STATE_STORED)->value('id')
                ?? Pallet::factory()->stored(),
            'store_id'  => Store::query()->where('type', 'interno')->value('id')
                ?? Store::factory(),
            'position'  => $this->faker->optional(0.8)->numberBetween(1, 50),
        ];
    }

    public function withPosition(): static
    {
        return $this->state(fn () => ['position' => $this->faker->numberBetween(1, 50)]);
    }

    public function withoutPosition(): static
    {
        return $this->state(fn () => ['position' => null]);
    }
}
