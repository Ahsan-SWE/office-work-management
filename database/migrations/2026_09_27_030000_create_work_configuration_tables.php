<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('asset_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('social_activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_full_activity_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('custom_job_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160)->unique();
            $table->text('default_instruction')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('qc_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('name', 180);
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->unique(['type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_reasons');
        Schema::dropIfExists('custom_job_types');
        Schema::dropIfExists('social_activity_types');
        Schema::dropIfExists('asset_sections');
        Schema::dropIfExists('tiers');
    }
};
