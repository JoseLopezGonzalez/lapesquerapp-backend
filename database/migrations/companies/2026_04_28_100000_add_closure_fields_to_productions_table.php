<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->unsignedBigInteger('closed_by')->nullable()->after('closed_at');
            $table->string('closure_reason')->nullable()->after('closed_by');
            $table->timestamp('reopened_at')->nullable()->after('closure_reason');
            $table->unsignedBigInteger('reopened_by')->nullable()->after('reopened_at');
            $table->string('reopen_reason')->nullable()->after('reopened_by');
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropColumn(['closed_by', 'closure_reason', 'reopened_at', 'reopened_by', 'reopen_reason']);
        });
    }
};
