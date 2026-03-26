<?php

namespace Database\Factories;

use App\Models\Prospect;
use App\Models\ProspectContact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProspectContactFactory extends Factory
{
    protected $model = ProspectContact::class;

    public function definition(): array
    {
        return [
            'prospect_id' => Prospect::query()->value('id') ?? Prospect::factory(),
            'name' => $this->faker->name(),
            'role' => $this->faker->jobTitle(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function forProspect(Prospect|int $prospect): static
    {
        $prospectId = $prospect instanceof Prospect ? $prospect->id : $prospect;

        return $this->state(fn () => [
            'prospect_id' => $prospectId,
        ]);
    }
}
