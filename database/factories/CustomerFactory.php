<?php

namespace Database\Factories;

use App\Models\Country;
use App\Models\Customer;
use App\Models\FieldOperator;
use App\Models\PaymentTerm;
use App\Models\Salesperson;
use App\Models\Transport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => 'Cliente ' . $this->faker->unique()->company(),
            'vat_number' => 'ES' . $this->faker->unique()->bothify('?????????'),
            'payment_term_id' => PaymentTerm::query()->value('id') ?? PaymentTerm::factory(),
            'billing_address' => $this->faker->address(),
            'shipping_address' => $this->faker->address(),
            'transportation_notes' => $this->faker->optional(0.3)->sentence(),
            'production_notes' => $this->faker->optional(0.2)->sentence(),
            'accounting_notes' => $this->faker->optional(0.15)->sentence(),
            'salesperson_id' => Salesperson::query()->value('id') ?? Salesperson::factory(),
            'field_operator_id' => $this->faker->optional(0.4)->passthrough(FieldOperator::query()->value('id') ?? FieldOperator::factory()),
            'operational_status' => $this->faker->randomElement(['active', 'paused']),
            'created_by_user_id' => User::query()->value('id') ?? User::factory(),
            'emails' => $this->faker->companyEmail() . '; CC:' . $this->faker->safeEmail(),
            'contact_info' => $this->faker->name() . ' - ' . $this->faker->phoneNumber(),
            'country_id' => Country::query()->value('id') ?? Country::factory(),
            'transport_id' => Transport::query()->value('id') ?? Transport::factory(),
            'a3erp_code' => $this->faker->optional(0.35)->numerify('A3###'),
            'facilcom_code' => $this->faker->optional(0.35)->numerify('FC###'),
            'alias' => 'Cliente Nº' . $this->faker->numberBetween(10, 999),
        ];
    }

    public function withAlias(): static
    {
        return $this->state(fn () => [
            'alias' => 'Cliente Nº' . $this->faker->numberBetween(10, 999),
        ]);
    }

    public function withoutAlias(): static
    {
        return $this->state(fn () => [
            'alias' => null,
        ]);
    }
}
