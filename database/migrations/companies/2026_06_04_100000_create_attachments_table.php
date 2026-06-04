<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        Schema::connection('tenant')->create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('collection');
            $table->string('disk')->default('attachments');
            $table->string('path');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type');
            $table->string('extension', 20);
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64)->nullable();
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->string('notes', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['attachable_type', 'attachable_id'], 'attachments_attachable_index');
            $table->index('collection');
            $table->index('uploaded_by_user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('attachments');
    }
};
