<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_code', 20)->nullable()->unique();
            $table->string('name', 220);
            $table->string('normalized_name', 220)->index();

            $table->text('google_sheet_url');
            $table->foreignId('current_tier_id')->nullable()->constrained('tiers')->nullOnDelete();

            $table->string('status', 20)->default('ACTIVE')->index();
            $table->date('start_date')->nullable();
            $table->date('expected_end_date')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz();

            $table->index(['status', 'name']);
            $table->index(['current_tier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
