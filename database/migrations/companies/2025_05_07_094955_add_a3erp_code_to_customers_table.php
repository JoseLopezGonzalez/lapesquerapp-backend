<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Verificar si la columna ya existe (puede haber sido agregada por otra migración)
        if (!Schema::hasColumn('customers', 'a3erp_code')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('a3erp_code')->nullable()->after('name'); // o después del campo que veas conveniente
            });
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('a3erp_code');
        });
    }
};
