<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('system_settings', function(Blueprint $table){
            $table->string('key',120)->primary();
            $table->text('value')->nullable();
            $table->timestampsTz();
        });

        Schema::create('code_counters', function(Blueprint $table){
            $table->string('key',40)->primary();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestampsTz();
        });

        Schema::create('work_orders', function(Blueprint $table){
            $table->id();
            $table->string('work_code',24)->nullable()->unique();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('work_type',20)->index();
            $table->string('status',30)->default('PENDING')->index();
            $table->string('priority',20)->default('NORMAL')->index();
            $table->text('sheet_url_snapshot');
            $table->jsonb('configuration_snapshot')->nullable();
            $table->foreignId('custom_job_type_id')->nullable()->constrained('custom_job_types')->nullOnDelete();
            $table->string('title',220)->nullable();
            $table->text('instruction')->nullable();
            $table->boolean('proof_required')->default(false);
            $table->boolean('special_custom_split')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['client_id','created_at']);
            $table->index(['created_by','created_at']);
            $table->index(['work_type','status','created_at']);
        });

        Schema::create('assignments', function(Blueprint $table){
            $table->id();
            $table->string('assignment_code',24)->nullable()->unique();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('asset_sections')->nullOnDelete();
            $table->string('scope_type',30);
            $table->text('scope_text')->nullable();
            $table->jsonb('scope_snapshot')->nullable();
            $table->unsignedInteger('assigned_count')->nullable();
            $table->unsignedInteger('completed_count')->default(0);
            $table->string('priority',20)->default('NORMAL')->index();
            $table->string('status',30)->default('PENDING')->index();
            $table->boolean('proof_required')->default(false);
            $table->text('instruction')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('assigned_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['employee_id','status','priority','assigned_at']);
            $table->index(['work_order_id','status']);
            $table->index(['section_id','status']);
        });

        Schema::create('assignment_progress_logs', function(Blueprint $table){
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type',30);
            $table->unsignedInteger('from_count')->default(0);
            $table->unsignedInteger('to_count')->default(0);
            $table->text('note')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['assignment_id','created_at']);
        });

        Schema::create('assignment_reassignments', function(Blueprint $table){
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('from_employee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_employee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['assignment_id','created_at']);
        });

        DB::statement("ALTER TABLE work_orders ADD CONSTRAINT work_orders_work_type_check CHECK (work_type IN ('ASSETS','SOCIAL','CUSTOM'))");
        DB::statement("ALTER TABLE work_orders ADD CONSTRAINT work_orders_status_check CHECK (status IN ('PENDING','IN_PROGRESS','QC_IN_PROGRESS','REWORK','COMPLETED','CANCELLED','DUPLICATE'))");
        DB::statement("ALTER TABLE assignments ADD CONSTRAINT assignments_status_check CHECK (status IN ('PENDING','ONGOING','SUBMITTED_QC','QC_REVIEWING','REWORK','COMPLETED','DUPLICATE_REVIEW','DUPLICATE_CONFIRMED','CANCELLED'))");
        DB::statement("ALTER TABLE assignments ADD CONSTRAINT assignments_scope_type_check CHECK (scope_type IN ('FULL_SECTION','SPECIFIC_SITES','CUSTOM_SCOPE'))");
        DB::statement("ALTER TABLE assignments ADD CONSTRAINT assignments_counts_check CHECK (assigned_count IS NULL OR completed_count <= assigned_count)");
    }
    public function down(): void {
        Schema::dropIfExists('assignment_reassignments');
        Schema::dropIfExists('assignment_progress_logs');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('code_counters');
        Schema::dropIfExists('system_settings');
    }
};
