<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->dropIfExists('activity_logs');
    }

    public function down(): void
    {
        // Intentionally empty — the ActivityLog system has been removed.
    }
};
