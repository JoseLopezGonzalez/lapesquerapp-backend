<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

/**
 * Empleados de desarrollo — entorno tipo producción.
 * Inspirado en patrones reales: name (corto), nfc_uid (numérico string, único).
 * Solo añade los que no existan (firstOrCreate por nfc_uid).
 */
class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $names = ['Lolo', 'Jose', 'Pedro', 'Antonio', 'Adan', 'Maria', 'Carlos'];

        foreach ($names as $i => $name) {
            $nfcUid = (string) (1600000000 + 5600 + $i);
            Employee::firstOrCreate(
                ['name' => $name],
                ['nfc_uid' => $nfcUid]
            );
        }

        Employee::firstOrCreate(
            ['name' => 'Empleado sin NFC'],
            ['nfc_uid' => '0']
        );
    }
}
