<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PunchEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PunchEvent>
 */
class PunchEventFactory extends Factory
{
    protected $model = PunchEvent::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::query()->value('id') ?? Employee::factory(),
            'event_type'  => $this->faker->randomElement([PunchEvent::TYPE_IN, PunchEvent::TYPE_OUT]),
            'device_id'   => $this->faker->optional(0.6)->numerify('DEVICE-###'),
            'timestamp'   => Carbon::now()->subDays($this->faker->numberBetween(0, 30))
                ->setTime($this->faker->numberBetween(6, 21), $this->faker->numberBetween(0, 59)),
        ];
    }

    public function in(): static
    {
        return $this->state(fn () => ['event_type' => PunchEvent::TYPE_IN]);
    }

    public function out(): static
    {
        return $this->state(fn () => ['event_type' => PunchEvent::TYPE_OUT]);
    }

    /** Edge case: segundo IN consecutivo sin OUT previo */
    public function doubleIn(): static
    {
        return $this->state(fn () => ['event_type' => PunchEvent::TYPE_IN]);
    }

    /** Edge case: OUT sin IN previo */
    public function orphanOut(): static
    {
        return $this->state(fn () => ['event_type' => PunchEvent::TYPE_OUT]);
    }
}
