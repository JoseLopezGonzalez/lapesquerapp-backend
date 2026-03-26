<?php

namespace Database\Factories;

use App\Models\Process;
use App\Models\Production;
use App\Models\ProductionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionRecordFactory extends Factory
{
    protected $model = ProductionRecord::class;

    public function definition(): array
    {
        return [
            'production_id' => Production::query()->value('id') ?? Production::factory(),
            'parent_record_id' => null,
            'process_id' => Process::query()->value('id') ?? Process::factory(),
            'started_at' => now()->subHours(2),
            'finished_at' => null,
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    public function root(): static
    {
        return $this->state(fn () => [
            'parent_record_id' => null,
        ]);
    }

    public function childOf(ProductionRecord|int $parentRecord): static
    {
        $parent = $parentRecord instanceof ProductionRecord ? $parentRecord : ProductionRecord::query()->findOrFail($parentRecord);

        return $this->state(fn () => [
            'production_id' => $parent->production_id,
            'parent_record_id' => $parent->id,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'started_at' => now()->subHours(4),
            'finished_at' => now()->subHour(),
        ]);
    }
}
