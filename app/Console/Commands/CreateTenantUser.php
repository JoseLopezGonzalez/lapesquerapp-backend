<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateTenantUser extends Command
{
    protected $signature = 'tenant:create-user 
                            {subdomain : El subdomain del tenant}
                            {email : El email del usuario}
                            {--name= : El nombre del usuario (opcional)}
                            {--role= : El nombre del rol a asignar (opcional)}';

    protected $description = 'Crea un usuario en un tenant (acceso por magic link u OTP; sin contraseña)';

    public function handle(): int
    {
        $subdomain = $this->argument('subdomain');
        $email = $this->argument('email');
        $name = $this->option('name') ?: explode('@', $email)[0];
        $roleName = $this->option('role');

        // Buscar el tenant
        $tenant = Tenant::where('subdomain', $subdomain)->first();

        if (! $tenant) {
            $this->error("❌ No se encontró el tenant con subdomain: {$subdomain}");

            return Command::FAILURE;
        }

        if (! $tenant->is_active) {
            $this->error("❌ El tenant {$subdomain} no está activo (status: {$tenant->status})");

            return Command::FAILURE;
        }

        $this->info("📋 Tenant encontrado: {$tenant->name} (Base de datos: {$tenant->database})");

        // Configurar la conexión del tenant
        DB::purge('tenant');
        config(['database.connections.tenant.database' => $tenant->database]);
        DB::reconnect('tenant');

        // Verificar si el usuario ya existe
        $existingUser = User::on('tenant')->where('email', $email)->first();
        if ($existingUser) {
            $this->warn("⚠️  El usuario con email {$email} ya existe en este tenant.");

            return Command::SUCCESS;
        }

        if ($roleName && ! in_array($roleName, Role::values(), true)) {
            $this->error("❌ Rol inválido: {$roleName}. Valores permitidos: ".implode(', ', Role::values()));

            return Command::FAILURE;
        }

        try {
            $user = new User;
            $user->setConnection('tenant');
            $user->name = $name;
            $user->email = $email;
            $user->role = $roleName ?? Role::Operario->value;
            $user->save();

            $this->info('✅ Usuario creado exitosamente:');
            $this->line("   - ID: {$user->id}");
            $this->line("   - Nombre: {$user->name}");
            $this->line("   - Email: {$user->email}");
            $this->line("   - Rol: {$user->role}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error al crear el usuario: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
