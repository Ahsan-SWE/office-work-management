<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qc_reviews', function (Blueprint $table) {
            $table->boolean('is_major_error')->default(false)->after('negative_points')->index();
            $table->timestampTz('major_error_email_sent_at')->nullable()->after('major_error_email_sent');
            $table->timestampTz('released_at')->nullable()->after('reviewed_at');
            $table->foreignId('released_by')->nullable()->after('released_at')->constrained('users')->nullOnDelete();
            $table->text('release_reason')->nullable()->after('released_by');
        });

        Schema::create('qc_review_lock_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_review_id')->constrained('qc_reviews')->cascadeOnDelete();
            $table->string('event_type', 20);
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestampTz('created_at');

            $table->index(['qc_review_id', 'created_at']);
        });

        Schema::create('qc_gmail_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('google_user_id', 255);
            $table->string('email', 320);
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->json('scopes')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('connected_at')->nullable();
            $table->timestampTz('disconnected_at')->nullable();
            $table->timestampTz('last_refreshed_at')->nullable();
            $table->timestampTz('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestampsTz();
        });

        Schema::create('qc_major_error_email_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_review_id')->constrained('qc_reviews')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->string('recipient_email', 320);
            $table->json('cc_emails');
            $table->json('extra_cc_emails')->nullable();
            $table->string('subject', 500);
            $table->text('body');
            $table->string('status', 20)->index();
            $table->string('gmail_message_id', 255)->nullable();
            $table->timestampTz('attempted_at');
            $table->timestampTz('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampsTz();

            $table->unique(['qc_review_id', 'attempt_no']);
            $table->index(['sender_user_id', 'attempted_at']);
        });

        Schema::create('qc_review_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_review_id')->unique()->constrained('qc_reviews')->cascadeOnDelete();
            $table->foreignId('raised_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('OPEN')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestampTz('opened_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('qc_review_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_review_id')->constrained('qc_reviews')->cascadeOnDelete();
            $table->foreignId('super_admin_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('approved_count');
            $table->unsignedInteger('rework_count');
            $table->string('result', 30);
            $table->unsignedTinyInteger('negative_points');
            $table->unsignedTinyInteger('bonus_points');
            $table->boolean('is_major_error')->default(false);
            $table->text('reason');
            $table->timestampsTz();

            $table->index(['qc_review_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_review_overrides');
        Schema::dropIfExists('qc_review_escalations');
        Schema::dropIfExists('qc_major_error_email_attempts');
        Schema::dropIfExists('qc_gmail_connections');
        Schema::dropIfExists('qc_review_lock_events');

        Schema::table('qc_reviews', function (Blueprint $table) {
            $table->dropForeign(['released_by']);
            $table->dropColumn([
                'is_major_error',
                'major_error_email_sent_at',
                'released_at',
                'released_by',
                'release_reason',
            ]);
        });
    }
};
