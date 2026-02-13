<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\PunchEvent;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

/**
 * Eventos de fichaje de desarrollo — entorno tipo producción.
 * Inspirado en patrones reales: employee_id, event_type (IN/OUT), device_id (manual-web-interface / nfc-reader-web-interface), timestamp.
 * Solo añade hasta TARGET_EVENTS; no borra datos existentes.
 */
class PunchEventSeeder extends Seeder
{
    private const TARGET_EVENTS = 40;

    public function run(): void
    {
        $employees = Employee::all();
        if ($employees->isEmpty()) {
            $this->command->warn('PunchEventSeeder: Ejecuta antes EmployeeSeeder.');
            return;
        }

        $toCreate = max(0, self::TARGET_EVENTS - PunchEvent::count());
        if ($toCreate === 0) {
            return;
        }

        $faker = Faker::create('es_ES');
        $faker->seed(5610);

        $deviceIds = ['manual-web-interface', 'nfc-reader-web-interface'];

        for ($i = 0; $i < $toCreate; $i++) {
            PunchEvent::create([
                'employee_id' => $employees->random()->id,
                'event_type' => $faker->randomElement([PunchEvent::TYPE_IN, PunchEvent::TYPE_OUT]),
                'device_id' => $faker->randomElement($deviceIds),
                'timestamp' => $faker->dateTimeBetween('-7 days', 'now'),
            ]);
        }
    }
}
