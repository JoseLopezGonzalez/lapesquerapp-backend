<?php

namespace Database\Factories;

use App\Models\FishingGear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FishingGear>
 */
class FishingGearFactory extends Factory
{
    protected $model = FishingGear::class;

    public function definition(): array
    {
        $artes = [
            'Arrastre de fondo', 'Cerco', 'Palangre de fondo', 'Palangre de superficie',
            'Trasmallo', 'Arte de xeito', 'Volanta', 'Nasa', 'Marisqueo a pie',
            'Marisqueo a flote', 'Buceo', 'Curricán',
        ];

        return [
            'name' => $this->faker->unique()->randomElement($artes),
        ];
    }
}
