<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_sheet_url_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->text('url');
            $table->timestampTz('effective_at');
            $table->timestampTz('ended_at')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'effective_at']);
            $table->index(['client_id', 'ended_at']);
        });

        Schema::create('client_tier_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('tier_id')->constrained('tiers')->restrictOnDelete();
            $table->timestampTz('effective_at');
            $table->timestampTz('ended_at')->nullable();
            $table->string('change_reason', 160)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['client_id', 'effective_at']);
            $table->index(['client_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_tier_histories');
        Schema::dropIfExists('client_sheet_url_histories');
    }
};
