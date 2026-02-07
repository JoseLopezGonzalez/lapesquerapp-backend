<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Idempotente: no falla si la tabla ya existe (p. ej. tenant con migraciones desincronizadas).
     */
    public function up()
    {
        // Verificar que las tablas users y roles existen antes de crear role_user
        if (!Schema::hasTable('users') || !Schema::hasTable('roles')) {
            return;
        }

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Relación con usuarios
            $table->foreignId('role_id')->constrained()->onDelete('cascade'); // Relación con roles
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
