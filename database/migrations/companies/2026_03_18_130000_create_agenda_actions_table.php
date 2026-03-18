<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_actions', function (Blueprint $table) {
            $table->id();

            $table->string('target_type', 16);
            $table->unsignedBigInteger('target_id');

            $table->date('scheduled_at');
            $table->string('description', 255)->nullable();
            $table->string('status', 32)->default('pending');

            // Trazabilidad con “siguiente paso” y “hecho” mediante interacciones.
            $table->foreignId('source_interaction_id')->nullable()->constrained('commercial_interactions')->nullOnDelete();
            $table->foreignId('completed_interaction_id')->nullable()->constrained('commercial_interactions')->nullOnDelete();

            // Encadenado histórico de reprogramaciones.
            $table->foreignId('previous_action_id')->nullable()->constrained('agenda_actions')->nullOnDelete();

            $table->timestamps();

            $table->index(['target_type', 'target_id', 'status']);
            $table->index(['scheduled_at']);
            $table->index(['completed_interaction_id']);
        });

        DB::statement('
            ALTER TABLE agenda_actions
            ADD CONSTRAINT chk_agenda_actions_target_type
            CHECK (target_type IN (\'prospect\', \'customer\'))
        ');

        DB::statement('
            ALTER TABLE agenda_actions
            ADD CONSTRAINT chk_agenda_actions_status
            CHECK (status IN (\'pending\', \'done\', \'cancelled\'))
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_actions');
    }
};

