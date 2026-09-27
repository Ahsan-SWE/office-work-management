<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_monthly_quality_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->date('performance_month');
            $table->unsignedInteger('review_count');
            $table->unsignedInteger('negative_points');
            $table->unsignedInteger('bonus_points');
            $table->string('rating', 32);
            $table->timestampTz('calculated_at');
            $table->timestampsTz();

            $table->unique(['employee_id', 'performance_month']);
            $table->index('performance_month');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_monthly_quality_performances');
    }
};
