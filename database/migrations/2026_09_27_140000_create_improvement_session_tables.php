<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('improvement_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->date('performance_month');
            $table->unsignedInteger('trigger_negative_points');
            $table->unsignedInteger('trigger_bonus_points');
            $table->string('trigger_rating', 32);
            $table->foreignId('trigger_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('status', 48);

            $table->text('improvement_plan')->nullable();
            $table->text('team_leader_notes')->nullable();
            $table->text('employee_visible_notes')->nullable();
            $table->date('follow_up_date')->nullable();

            $table->timestampTz('opened_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('repeated_poor_alerted_at')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['employee_id', 'performance_month']);
            $table->index('status');
            $table->index('performance_month');
            $table->index(['employee_id', 'status']);
        });

        Schema::create('improvement_session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('improvement_session_id')
                ->constrained('improvement_sessions')
                ->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 64);
            $table->string('from_status', 48)->nullable();
            $table->string('to_status', 48)->nullable();
            $table->text('note')->nullable();
            $table->string('visibility', 24);
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['improvement_session_id', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_session_events');
        Schema::dropIfExists('improvement_sessions');
    }
};
