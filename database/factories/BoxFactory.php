<?php

namespace Database\Factories;

use App\Models\Box;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Box>
 */
class BoxFactory extends Factory
{
    protected $model = Box::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $netWeight = $this->faker->randomFloat(2, 4, 28);
        $lot = $this->faker->dateTimeBetween('-4 months', 'now')->format('dmy');

        return [
            'article_id' => Product::query()->value('id') ?? Product::factory(),
            'lot' => $lot,
            'gs1_128' => '(01)' . $this->faker->numerify('984' . str_repeat('#', 11)) . '(3100)' . str_pad((string) round($netWeight * 100), 6, '0', STR_PAD_LEFT) . '(10)' . $lot,
            'gross_weight' => $netWeight + $this->faker->randomFloat(2, 0.1, 1.2),
            'net_weight' => $netWeight,
        ];
    }
}
