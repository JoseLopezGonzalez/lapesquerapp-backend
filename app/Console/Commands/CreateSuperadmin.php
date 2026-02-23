<?php

namespace App\Console\Commands;

use App\Models\SuperadminUser;
use Illuminate\Console\Command;

class CreateSuperadmin extends Command
{
    protected $signature = 'superadmin:create
                            {email : Email del superadmin}
                            {--name= : Nombre del superadmin}';

    protected $description = 'Crea un usuario superadmin en la BD central';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->option('name') ?: explode('@', $email)[0];

        $existing = SuperadminUser::where('email', $email)->first();
        if ($existing) {
            $this->warn("Ya existe un superadmin con email {$email} (ID: {$existing->id}).");
            return Command::SUCCESS;
        }

        $user = SuperadminUser::create([
            'name' => $name,
            'email' => $email,
        ]);

        $this->info("Superadmin creado:");
        $this->line("  ID: {$user->id}");
        $this->line("  Nombre: {$user->name}");
        $this->line("  Email: {$user->email}");

        return Command::SUCCESS;
    }
}
