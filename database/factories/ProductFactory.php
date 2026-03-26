<?php

namespace Database\Factories;

use App\Models\CaptureZone;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Producto ' . $this->faker->unique()->words(2, true),
            'family_id' => ProductFamily::query()->value('id'),
            'species_id' => Species::query()->value('id') ?? Species::factory(),
            'capture_zone_id' => CaptureZone::query()->value('id') ?? CaptureZone::factory(),
            'article_gtin' => $this->faker->numerify('84' . str_repeat('#', 11)),
            'box_gtin' => $this->faker->numerify('984' . str_repeat('#', 11)),
            'pallet_gtin' => $this->faker->numerify('984' . str_repeat('#', 11)),
            'a3erp_code' => $this->faker->optional(0.35)->numerify('10###'),
            'facil_com_code' => $this->faker->optional(0.45)->numerify('##'),
        ];
    }
}
