<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        $exportType = $this->faker->randomElement(['facilcom', 'a3erp']);

        return [
            'name' => 'Proveedor ' . $this->faker->unique()->company(),
            'type' => $this->faker->randomElement(['raw_material', '']),
            'contact_person' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'emails' => $this->faker->companyEmail() . ';',
            'address' => $this->faker->address(),
            'cebo_export_type' => $exportType,
            'facil_com_code' => $this->faker->optional(0.75)->numerify('##'),
            'a3erp_cebo_code' => $exportType === 'a3erp' ? $this->faker->numerify('######') : null,
            'facilcom_cebo_code' => $exportType === 'facilcom' ? $this->faker->numerify('##') : null,
        ];
    }
}
