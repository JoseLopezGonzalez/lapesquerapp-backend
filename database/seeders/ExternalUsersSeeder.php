<?php

namespace Database\Seeders;

use App\Models\ExternalUser;
use Illuminate\Database\Seeder;

class ExternalUsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Maquila Frío Sur',
                'company_name' => 'Frío Sur S.L.',
                'email' => 'maquila1@pesquerapp.test',
                'type' => ExternalUser::TYPE_MAQUILADOR,
                'is_active' => true,
                'notes' => 'Proveedor externo de maquila principal.',
            ],
            [
                'name' => 'Maquila Costa Norte',
                'company_name' => 'Costa Norte Procesados S.L.',
                'email' => 'maquila2@pesquerapp.test',
                'type' => ExternalUser::TYPE_MAQUILADOR,
                'is_active' => true,
                'notes' => 'Proveedor externo secundario para picos de producción.',
            ],
        ];

        foreach ($users as $user) {
            ExternalUser::updateOrCreate(
                ['email' => $user['email']],
                $user
            );
        }
    }
}
