<?php

namespace Database\Factories;

use App\Models\ExternalProcessor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExternalProcessorFactory extends Factory
{
    protected $model = ExternalProcessor::class;

    public function definition(): array
    {
        return [
            'name' => 'Maquilador '.$this->faker->unique()->company(),
            'legal_name' => $this->faker->optional()->company(),
            'vat_number' => strtoupper($this->faker->unique()->bothify('?########')),
            'sanitary_registration_number' => $this->faker->optional()->bothify('##.#####/??'),
            'contact_person' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'emails' => $this->faker->companyEmail().';',
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'postal_code' => $this->faker->postcode(),
            'province' => $this->faker->state(),
            'country_id' => null,
            'is_active' => true,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
