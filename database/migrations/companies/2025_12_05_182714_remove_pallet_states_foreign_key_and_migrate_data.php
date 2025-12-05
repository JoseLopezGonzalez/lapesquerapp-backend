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
     * Esta migración:
     * 1. Migra los datos: palets con state_id=3 sin order_id → cambian a 4 (processed)
     * 2. Elimina la foreign key a pallet_states
     * 3. Elimina la tabla pallet_states
     */
    public function up(): void
    {
        if (!Schema::hasTable('pallets')) {
            return;
        }

        // Paso 1: Eliminar la foreign key constraint PRIMERO (antes de actualizar datos)
        // Para sistemas multi-tenant, necesitamos eliminar la FK manualmente
        $connection = DB::connection();
        $driverName = $connection->getDriverName();
        
        if ($driverName === 'pgsql') {
            // PostgreSQL: eliminar constraint por nombre
            // El nombre típico es: tenants_pallets_state_id_foreign
            // Intentar con el esquema actual
            $schema = DB::getDatabaseName();
            $constraints = DB::select(
                "SELECT conname 
                 FROM pg_constraint 
                 WHERE conrelid = 'pallets'::regclass 
                 AND contype = 'f'
                 AND conname LIKE '%state_id%'"
            );
            
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE pallets DROP CONSTRAINT IF EXISTS {$constraint->conname}");
            }
        } else {
            // MySQL u otros: usar Schema
            try {
                Schema::table('pallets', function (Blueprint $table) {
                    $table->dropForeign(['state_id']);
                });
            } catch (\Exception $e) {
                // La FK no existe, continuar
            }
        }

        // Paso 2: Migrar datos existentes (ahora que la FK está eliminada)
        // Palets con state_id = 3 (enviado) que NO tienen order_id → cambian a 4 (procesado)
        if (Schema::hasTable('pallets')) {
            DB::table('pallets')
                ->where('state_id', 3)
                ->whereNull('order_id')
                ->update(['state_id' => 4]);
        }

        // Paso 3: Eliminar la tabla pallet_states si existe
        Schema::dropIfExists('pallet_states');
    }

    /**
     * Reverse the migrations.
     * 
     * NOTA: Esta migración es difícil de revertir completamente porque perdemos
     * la información de la tabla pallet_states original. Se recrea la tabla
     * pero los datos se pierden.
     */
    public function down(): void
    {
        // Recrear tabla pallet_states
        Schema::create('pallet_states', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // Insertar estados básicos (asumiendo los valores estándar)
        DB::table('pallet_states')->insert([
            ['id' => 1, 'name' => 'registered', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'stored', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'shipped', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'processed', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Revertir migración de datos: palets con state_id=4 → cambian a 3
        DB::table('pallets')
            ->where('state_id', 4)
            ->update(['state_id' => 3]);

        // Recrear foreign key
        Schema::table('pallets', function (Blueprint $table) {
            $table->foreign('state_id')->references('id')->on('pallet_states');
        });
    }
};
