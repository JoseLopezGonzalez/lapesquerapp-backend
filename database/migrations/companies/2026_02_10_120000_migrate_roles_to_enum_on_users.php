<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migración: roles como enum en columna users.role.
     * - Añade users.role (string).
     * - Migra datos desde role_user + roles con mapeo legacy → nuevo.
     * - Elimina role_user y roles.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        // 1. Añadir columna role (nullable para poder migrar)
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 50)->nullable()->after('company_logo_url');
            });
        }

        // 2. Migrar datos desde role_user + roles si existen
        if (Schema::hasTable('roles') && Schema::hasTable('role_user')) {
            $legacyToNew = [
                'superuser' => 'tecnico',
                'manager' => 'administrador',
                'admin' => 'administracion',
                'store_operator' => 'operario',
            ];
            $priority = ['superuser' => 0, 'manager' => 1, 'admin' => 2, 'store_operator' => 3];

            $users = DB::table('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->select('role_user.user_id', 'roles.name as role_name')
                ->get()
                ->groupBy('user_id');

            foreach ($users as $userId => $rows) {
                $roleNames = $rows->pluck('role_name')->unique()->values()->all();
                $chosen = null;
                $bestPriority = 999;
                foreach ($roleNames as $name) {
                    $p = $priority[$name] ?? 999;
                    if ($p < $bestPriority && isset($legacyToNew[$name])) {
                        $bestPriority = $p;
                        $chosen = $legacyToNew[$name];
                    }
                }
                if ($chosen !== null) {
                    DB::table('users')->where('id', $userId)->update(['role' => $chosen]);
                }
            }
        }

        // 3. Usuarios sin rol → operario
        DB::table('users')->whereNull('role')->update(['role' => 'operario']);

        // 4. role not nullable con default
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'operario'");
        } elseif ($driver !== 'sqlite') {
            // PostgreSQL y otros: requiere doctrine/dbal para ->change()
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('role', 50)->default('operario')->nullable(false)->change();
                });
            } catch (\Throwable $e) {
                // Sin dbal: dejar columna nullable; la app siempre asigna valor
            }
        }
        // SQLite: dejar nullable; todos los registros ya tienen valor por paso 3

        // 5. Eliminar tablas
        if (Schema::hasTable('role_user')) {
            Schema::dropIfExists('role_user');
        }
        if (Schema::hasTable('roles')) {
            Schema::dropIfExists('roles');
        }
    }

    /**
     * Reverse: no recreamos roles/role_user (pérdida de información de múltiples roles).
     */
    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'role')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
