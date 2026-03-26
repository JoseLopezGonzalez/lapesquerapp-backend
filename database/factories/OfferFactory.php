<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Incoterm;
use App\Models\Offer;
use App\Models\PaymentTerm;
use App\Models\Prospect;
use App\Models\Salesperson;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfferFactory extends Factory
{
    protected $model = Offer::class;

    public function definition(): array
    {
        return [
            'prospect_id' => Prospect::query()->value('id') ?? Prospect::factory(),
            'customer_id' => null,
            'salesperson_id' => Salesperson::query()->value('id') ?? Salesperson::factory(),
            'status' => Offer::STATUS_DRAFT,
            'send_channel' => $this->faker->randomElement(Offer::sendChannels()),
            'sent_at' => null,
            'valid_until' => $this->faker->dateTimeBetween('+7 days', '+30 days')->format('Y-m-d'),
            'incoterm_id' => $this->faker->optional(0.5)->passthrough(Incoterm::query()->value('id') ?? Incoterm::factory()),
            'payment_term_id' => $this->faker->optional(0.5)->passthrough(PaymentTerm::query()->value('id') ?? PaymentTerm::factory()),
            'currency' => 'EUR',
            'notes' => $this->faker->optional(0.4)->sentence(),
            'accepted_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'order_id' => null,
        ];
    }

    public function forProspect(Prospect|int $prospect): static
    {
        $prospectModel = $prospect instanceof Prospect ? $prospect : Prospect::query()->findOrFail($prospect);

        return $this->state(fn () => [
            'prospect_id' => $prospectModel->id,
            'customer_id' => null,
            'salesperson_id' => $prospectModel->salesperson_id,
        ]);
    }

    public function forCustomer(Customer|int $customer): static
    {
        $customerModel = $customer instanceof Customer ? $customer : Customer::query()->findOrFail($customer);

        return $this->state(fn () => [
            'prospect_id' => null,
            'customer_id' => $customerModel->id,
            'salesperson_id' => $customerModel->salesperson_id,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Offer::STATUS_DRAFT,
            'sent_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'order_id' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => Offer::STATUS_SENT,
            'sent_at' => now()->subDay(),
            'accepted_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'order_id' => null,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => Offer::STATUS_ACCEPTED,
            'sent_at' => now()->subDays(3),
            'accepted_at' => now()->subDay(),
            'rejected_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => Offer::STATUS_REJECTED,
            'sent_at' => now()->subDays(2),
            'accepted_at' => null,
            'rejected_at' => now()->subDay(),
            'rejection_reason' => 'Precio fuera de mercado',
            'order_id' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => Offer::STATUS_EXPIRED,
            'sent_at' => now()->subDays(10),
            'valid_until' => now()->subDay()->format('Y-m-d'),
            'accepted_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'order_id' => null,
        ]);
    }
}
