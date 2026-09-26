<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type', 80)->default('GENERAL');
            $table->string('title', 180);
            $table->text('message')->nullable();

            $table->string('related_type', 120)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            $table->boolean('is_read')->default(false);
            $table->timestampTz('read_at')->nullable();

            $table->boolean('requires_action')->default(false);
            $table->timestampTz('action_completed_at')->nullable();

            $table->timestampsTz();

            $table->index(['user_id', 'is_read', 'created_at']);
            $table->index(['user_id', 'requires_action', 'action_completed_at']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
