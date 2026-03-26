<?php

namespace Database\Factories;

use App\Models\DeliveryRoute;
use App\Models\FieldOperator;
use App\Models\Salesperson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryRouteFactory extends Factory
{
    protected $model = DeliveryRoute::class;

    public function definition(): array
    {
        return [
            'route_template_id' => null,
            'name' => 'Ruta ' . $this->faker->unique()->city(),
            'description' => $this->faker->optional(0.35)->sentence(),
            'route_date' => $this->faker->dateTimeBetween('-5 days', '+10 days')->format('Y-m-d'),
            'status' => $this->faker->randomElement(DeliveryRoute::validStatuses()),
            'salesperson_id' => $this->faker->optional(0.75)->passthrough(Salesperson::query()->value('id') ?? Salesperson::factory()),
            'field_operator_id' => $this->faker->optional(0.75)->passthrough(FieldOperator::query()->value('id') ?? FieldOperator::factory()),
            'created_by_user_id' => User::query()->value('id') ?? User::factory(),
        ];
    }

    public function planned(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryRoute::STATUS_PLANNED,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryRoute::STATUS_IN_PROGRESS,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryRoute::STATUS_COMPLETED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryRoute::STATUS_CANCELLED,
        ]);
    }

    public function forSalesperson(Salesperson|int $salesperson): static
    {
        $salespersonId = $salesperson instanceof Salesperson ? $salesperson->id : $salesperson;

        return $this->state(fn () => [
            'salesperson_id' => $salespersonId,
            'field_operator_id' => null,
        ]);
    }

    public function forFieldOperator(FieldOperator|int $fieldOperator): static
    {
        $fieldOperatorId = $fieldOperator instanceof FieldOperator ? $fieldOperator->id : $fieldOperator;

        return $this->state(fn () => [
            'field_operator_id' => $fieldOperatorId,
        ]);
    }
}
