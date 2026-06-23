<?php

namespace Database\Seeders;

use App\Models\Salesperson;
use App\Models\User;
use App\Enums\Role;
use Illuminate\Database\Seeder;

class SalespeopleSeeder extends Seeder
{
    public function run(): void
    {
        $salespeople = [
            ['name' => 'Vicente',       'emails' => 'vicente@comercial.es;'],
            ['name' => 'María',         'emails' => 'maria@comercial.es;'],
            ['name' => 'Carlos García', 'emails' => 'carlos.garcia@comercial.es;'],
            ['name' => 'Ana',           'emails' => 'ana@comercial.es;'],
            ['name' => 'Luis Fernández','emails' => 'luis.fernandez@comercial.es;'],
            ['name' => 'Elena',         'emails' => 'elena@comercial.es;'],
        ];

        foreach ($salespeople as $data) {
            Salesperson::firstOrCreate(
                ['name' => $data['name']],
                ['emails' => $data['emails']]
            );
        }

        // Vincular un usuario con rol comercial a un comercial (para pruebas de scoping por salesperson)
        $comercialUser = User::where('role', Role::Comercial->value)->first();
        if ($comercialUser) {
            // Verificar que el usuario no esté ya asignado a otro salesperson
            $existingSalesperson = Salesperson::where('user_id', $comercialUser->id)->first();
            if (! $existingSalesperson) {
                $salespersonWithoutUser = Salesperson::whereNull('user_id')->first();
                if ($salespersonWithoutUser) {
                    $salespersonWithoutUser->update(['user_id' => $comercialUser->id]);
                }
            }
        }
    }
}
