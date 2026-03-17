<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->nullable()->constrained('prospects')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('salesperson_id')->constrained('salespeople')->cascadeOnDelete();
            $table->string('type', 32);
            $table->dateTime('occurred_at');
            $table->string('summary', 500);
            $table->string('result', 32);
            $table->string('next_action_note')->nullable();
            $table->date('next_action_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['salesperson_id', 'type']);
            $table->index(['salesperson_id', 'result']);
            $table->index('next_action_at');
        });

        DB::statement('
            ALTER TABLE commercial_interactions
            ADD CONSTRAINT chk_commercial_interactions_exactly_one_target
            CHECK (
                (prospect_id IS NOT NULL AND customer_id IS NULL)
                OR (prospect_id IS NULL AND customer_id IS NOT NULL)
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_interactions');
    }
};
