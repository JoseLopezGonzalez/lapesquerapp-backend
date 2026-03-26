<?php

namespace Database\Factories;

use App\Models\Box;
use App\Models\Store;
use App\Models\StoredBox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StoredBox>
 */
class StoredBoxFactory extends Factory
{
    protected $model = StoredBox::class;

    public function definition(): array
    {
        return [
            'box_id'   => Box::query()->value('id') ?? Box::factory(),
            'store_id' => Store::query()->where('type', 'interno')->value('id')
                ?? Store::factory(),
            'position' => $this->faker->optional(0.7)->numberBetween(1, 100),
        ];
    }

    public function withPosition(): static
    {
        return $this->state(fn () => ['position' => $this->faker->numberBetween(1, 100)]);
    }

    public function withoutPosition(): static
    {
        return $this->state(fn () => ['position' => null]);
    }
}
