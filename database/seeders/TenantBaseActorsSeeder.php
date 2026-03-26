<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TenantBaseActorsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UsersSeeder::class,
            RepartidorAutoventaDevSeeder::class,
            SalespeopleSeeder::class,
            SupplierSeeder::class,
            StoreSeeder::class,
            ExternalUsersSeeder::class,
            ExternalStoresSeeder::class,
            StoreOperatorUserSeeder::class,
            EmployeeSeeder::class,
            PunchEventSeeder::class,
            CustomerSeeder::class,
        ]);
    }
}
