<?php

use App\Models\ExternalUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::create('external_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->unique();
            $table->enum('type', [ExternalUser::TYPE_MAQUILADOR])->default(ExternalUser::TYPE_MAQUILADOR);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (config('database.default') !== 'tenant') {
            return;
        }

        Schema::dropIfExists('external_users');
    }
};
