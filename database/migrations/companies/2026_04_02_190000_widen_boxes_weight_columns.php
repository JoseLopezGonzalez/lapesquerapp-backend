<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * decimal(6,2) caps at 9999.99; larger weights (e.g. full pallet lines, industrial lots) must fit.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE boxes MODIFY gross_weight DECIMAL(12, 3) NOT NULL');
        DB::statement('ALTER TABLE boxes MODIFY net_weight DECIMAL(12, 3) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE boxes MODIFY gross_weight DECIMAL(6, 2) NOT NULL');
        DB::statement('ALTER TABLE boxes MODIFY net_weight DECIMAL(6, 2) NOT NULL');
    }
};
