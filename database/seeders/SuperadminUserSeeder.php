<?php

namespace Database\Seeders;

use App\Models\SuperadminUser;
use Illuminate\Database\Seeder;

/**
 * Seed superadmin users in the central database.
 * Auth is via magic link / OTP (no password).
 * Run: php artisan db:seed --class=SuperadminUserSeeder --force
 */
class SuperadminUserSeeder extends Seeder
{
    private const SUPERADMINS = [
        [
            'email' => 'tijito4@gmail.com',
            'name'  => 'Superadmin',
        ],
    ];

    public function run(): void
    {
        foreach (self::SUPERADMINS as $data) {
            $user = SuperadminUser::updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name']]
            );

            $this->command->info("Superadmin listo: {$user->email}");
        }
    }
}
