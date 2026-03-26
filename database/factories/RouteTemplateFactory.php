<?php

namespace Database\Factories;

use App\Models\FieldOperator;
use App\Models\RouteTemplate;
use App\Models\Salesperson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteTemplateFactory extends Factory
{
    protected $model = RouteTemplate::class;

    public function definition(): array
    {
        return [
            'name' => 'Plantilla ' . $this->faker->unique()->city(),
            'description' => $this->faker->optional(0.35)->sentence(),
            'salesperson_id' => $this->faker->optional(0.75)->passthrough(Salesperson::query()->value('id') ?? Salesperson::factory()),
            'field_operator_id' => $this->faker->optional(0.75)->passthrough(FieldOperator::query()->value('id') ?? FieldOperator::factory()),
            'created_by_user_id' => User::query()->value('id') ?? User::factory(),
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
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
