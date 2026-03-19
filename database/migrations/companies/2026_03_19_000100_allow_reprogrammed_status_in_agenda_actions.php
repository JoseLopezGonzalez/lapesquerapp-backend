<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // El constraint original fue creado en `2026_03_18_130000_create_agenda_actions_table.php`
        // con el nombre `chk_agenda_actions_status`.
        DB::statement('ALTER TABLE agenda_actions DROP CHECK chk_agenda_actions_status');

        DB::statement('
            ALTER TABLE agenda_actions
            ADD CONSTRAINT chk_agenda_actions_status
            CHECK (status IN (\'pending\', \'done\', \'cancelled\', \'reprogrammed\'))
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE agenda_actions DROP CHECK chk_agenda_actions_status');

        DB::statement('
            ALTER TABLE agenda_actions
            ADD CONSTRAINT chk_agenda_actions_status
            CHECK (status IN (\'pending\', \'done\', \'cancelled\'))
        ');
    }
};

