<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_actions', function (Blueprint $table) {
            if (! Schema::hasColumn('agenda_actions', 'reason')) {
                $table->text('reason')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agenda_actions', function (Blueprint $table) {
            if (Schema::hasColumn('agenda_actions', 'reason')) {
                $table->dropColumn('reason');
            }
        });
    }
};

