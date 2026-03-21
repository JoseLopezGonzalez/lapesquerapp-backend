<?php

use App\Models\RouteStop;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            $table->string('result_type', 32)->nullable()->after('status');
            $table->text('result_notes')->nullable()->after('result_type');
        });

        $allowed = "'" . implode("','", RouteStop::validResultTypes()) . "'";

        DB::statement("
            UPDATE route_stops
            SET result_type = result
            WHERE result IS NOT NULL
              AND result IN ({$allowed})
        ");

        DB::statement("
            UPDATE route_stops
            SET result_notes = result
            WHERE result IS NOT NULL
              AND result NOT IN ({$allowed})
        ");

        Schema::table('route_stops', function (Blueprint $table) {
            $table->dropColumn('result');
        });
    }

    public function down(): void
    {
        Schema::table('route_stops', function (Blueprint $table) {
            $table->string('result', 64)->nullable()->after('status');
        });

        DB::statement("
            UPDATE route_stops
            SET result = COALESCE(result_type, LEFT(result_notes, 64))
        ");

        Schema::table('route_stops', function (Blueprint $table) {
            $table->dropColumn(['result_type', 'result_notes']);
        });
    }
};
