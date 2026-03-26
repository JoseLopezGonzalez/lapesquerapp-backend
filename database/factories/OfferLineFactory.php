<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\OfferLine;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferLineFactory extends Factory
{
    protected $model = OfferLine::class;

    public function definition(): array
    {
        return [
            'offer_id' => Offer::query()->value('id') ?? Offer::factory(),
            'product_id' => $this->faker->optional(0.85)->passthrough(Product::query()->value('id') ?? Product::factory()),
            'description' => $this->faker->sentence(4),
            'quantity' => $this->faker->randomFloat(3, 1, 250),
            'unit' => $this->faker->randomElement(['kg', 'caja', 'palet']),
            'unit_price' => $this->faker->randomFloat(4, 1, 25),
            'tax_id' => $this->faker->optional(0.75)->passthrough(Tax::query()->value('id') ?? Tax::factory()),
            'boxes' => $this->faker->optional(0.65)->numberBetween(1, 30),
            'currency' => 'EUR',
        ];
    }

    public function forOffer(Offer|int $offer): static
    {
        $offerId = $offer instanceof Offer ? $offer->id : $offer;

        return $this->state(fn () => [
            'offer_id' => $offerId,
        ]);
    }

    public function forProduct(Product|int $product): static
    {
        $productId = $product instanceof Product ? $product->id : $product;

        return $this->state(fn () => [
            'product_id' => $productId,
        ]);
    }
}
