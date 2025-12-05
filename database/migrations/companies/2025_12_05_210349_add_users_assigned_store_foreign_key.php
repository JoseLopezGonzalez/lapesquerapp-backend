<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega la foreign key para assigned_store_id en users.
     * onDelete('set null') permite que un usuario quede sin almacén asignado
     * si se elimina el almacén.
     */
    public function up(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Verificar si la columna existe
            if (!Schema::hasColumn('users', 'assigned_store_id')) {
                $table->unsignedBigInteger('assigned_store_id')->nullable()->after('email_verified_at');
            }
        });
        
        // Limpiar datos inválidos: usuarios con assigned_store_id que no existe en stores
        if (Schema::hasTable('users') && Schema::hasTable('stores')) {
            $validStoreIds = DB::table('stores')->pluck('id')->toArray();
            if (!empty($validStoreIds)) {
                DB::table('users')
                    ->whereNotNull('assigned_store_id')
                    ->whereNotIn('assigned_store_id', $validStoreIds)
                    ->update(['assigned_store_id' => null]);
            } else {
                // Si no hay stores, poner todos los assigned_store_id a null
                DB::table('users')
                    ->whereNotNull('assigned_store_id')
                    ->update(['assigned_store_id' => null]);
            }
        }
        
        // Eliminar FK existente si existe (usando SQL directo para evitar errores)
        try {
            $fks = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'users' 
                AND COLUMN_NAME = 'assigned_store_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            foreach ($fks as $fk) {
                DB::statement("ALTER TABLE users DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
            }
        } catch (\Exception $e) {
            // La FK no existe o error al consultar, continuar
        }
        
        // Verificar que la tabla stores existe antes de crear la FK
        if (Schema::hasTable('stores')) {
            // Crear la foreign key con onDelete('set null')
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('assigned_store_id')
                    ->references('id')
                    ->on('stores')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Eliminar la foreign key
            try {
                $table->dropForeign(['assigned_store_id']);
            } catch (\Exception $e) {
                // La FK no existe, continuar
            }
        });
    }
};
