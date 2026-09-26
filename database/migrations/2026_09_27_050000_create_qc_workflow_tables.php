<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_code', 24)->nullable()->unique();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('submission_type', 20)->index();
            $table->unsignedInteger('submitted_count');
            $table->text('scope_text')->nullable();
            $table->text('employee_note')->nullable();
            $table->string('status', 20)->default('WAITING')->index();
            $table->timestampTz('submitted_at');
            $table->timestampsTz();

            $table->index(['assignment_id', 'status', 'submitted_at']);
            $table->index(['submitted_by', 'submitted_at']);
        });

        Schema::create('qc_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('review_code', 24)->nullable()->unique();
            $table->foreignId('qc_submission_id')->unique()->constrained('qc_submissions')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('responsible_employee_id')->constrained('users')->restrictOnDelete();

            $table->unsignedInteger('approved_count')->nullable();
            $table->unsignedInteger('rework_count')->nullable();
            $table->string('result', 30)->nullable()->index();

            $table->unsignedTinyInteger('bonus_points')->default(0);
            $table->unsignedTinyInteger('negative_points')->default(0);
            $table->text('review_comment')->nullable();

            $table->timestampTz('started_at');
            $table->timestampTz('reviewed_at')->nullable();
            $table->boolean('major_error_email_sent')->default(false);
            $table->timestampsTz();

            $table->index(['reviewer_id', 'started_at']);
            $table->index(['responsible_employee_id', 'reviewed_at']);
        });

        Schema::table('qc_submissions', function (Blueprint $table) {
            $table->foreignId('source_review_id')
                ->nullable()
                ->unique()
                ->after('employee_note')
                ->constrained('qc_reviews')
                ->restrictOnDelete();
        });

        Schema::create('qc_review_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_review_id')->constrained('qc_reviews')->cascadeOnDelete();
            $table->foreignId('negative_reason_id')->constrained('qc_reasons')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('asset_sections')->nullOnDelete();
            $table->string('site_name', 255)->nullable();
            $table->unsignedTinyInteger('negative_points')->default(0);
            $table->text('comment')->nullable();
            $table->timestampsTz();

            $table->index(['qc_review_id', 'negative_reason_id']);
        });

        Schema::create('qc_review_bonus_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_review_id')->constrained('qc_reviews')->cascadeOnDelete();
            $table->foreignId('bonus_reason_id')->constrained('qc_reasons')->restrictOnDelete();
            $table->unsignedTinyInteger('points');
            $table->text('comment')->nullable();
            $table->timestampsTz();

            $table->index(['qc_review_id', 'bonus_reason_id']);
        });

        DB::statement("ALTER TABLE qc_submissions ADD CONSTRAINT qc_submissions_type_check CHECK (submission_type IN ('PARTIAL','FINAL','REWORK'))");
        DB::statement("ALTER TABLE qc_submissions ADD CONSTRAINT qc_submissions_status_check CHECK (status IN ('WAITING','REVIEWING','REVIEWED'))");
        DB::statement("ALTER TABLE qc_reviews ADD CONSTRAINT qc_reviews_result_check CHECK (result IS NULL OR result IN ('APPROVED','REWORK_REQUIRED'))");
        DB::statement("ALTER TABLE qc_reviews ADD CONSTRAINT qc_reviews_points_check CHECK (bonus_points BETWEEN 0 AND 5 AND negative_points BETWEEN 0 AND 5)");
        DB::statement("ALTER TABLE qc_review_issues ADD CONSTRAINT qc_review_issues_points_check CHECK (negative_points BETWEEN 0 AND 5)");
        DB::statement("ALTER TABLE qc_review_bonus_items ADD CONSTRAINT qc_review_bonus_items_points_check CHECK (points BETWEEN 1 AND 5)");
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_review_bonus_items');
        Schema::dropIfExists('qc_review_issues');

        if (Schema::hasColumn('qc_submissions', 'source_review_id')) {
            Schema::table('qc_submissions', function (Blueprint $table) {
                $table->dropForeign(['source_review_id']);
                $table->dropUnique(['source_review_id']);
                $table->dropColumn('source_review_id');
            });
        }

        Schema::dropIfExists('qc_reviews');
        Schema::dropIfExists('qc_submissions');
    }
};
