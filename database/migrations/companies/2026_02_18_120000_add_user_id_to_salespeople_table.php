<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add user_id to salespeople for User–Salesperson linking (commercial role).
     * Salesperson is the master entity; User is the login account.
     *
     * @see docs/por-hacer/00-autorizacion-permisos-estado-completo.md section 4.1
     */
    public function up(): void
    {
        Schema::table('salespeople', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('emails')
                ->constrained('users')
                ->nullOnDelete();
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salespeople', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropForeign(['user_id']);
        });
    }
};
