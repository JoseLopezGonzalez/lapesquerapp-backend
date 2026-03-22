<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\FieldOperator;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuario de desarrollo con rol repartidor/autoventa y FieldOperator vinculado (perímetro field/*).
 * Misma convención de email que UsersSeeder (@pesquerapp.com). Acceso por magic link / OTP.
 */
class RepartidorAutoventaDevSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'repartidor@pesquerapp.com';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Miguel Repartidor',
                'role' => Role::RepartidorAutoventa->value,
                'active' => true,
            ]
        );

        if ($user->role !== Role::RepartidorAutoventa->value || ! $user->active) {
            $user->update([
                'role' => Role::RepartidorAutoventa->value,
                'active' => true,
            ]);
        }

        FieldOperator::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'Miguel Repartidor',
                'emails' => $email,
            ]
        );

        $this->command?->info("Repartidor dev: {$email} (rol repartidor_autoventa + field operator)");
    }
}
