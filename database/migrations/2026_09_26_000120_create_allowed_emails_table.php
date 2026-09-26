<?php
use App\Enums\AllowedEmailStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('allowed_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email',320)->unique();
            $table->string('preselected_role',30);
            $table->foreignId('preselected_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('preselected_qc_scope',20)->nullable();
            $table->string('status',20)->default(AllowedEmailStatus::PENDING->value);
            $table->foreignId('registered_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('registered_at')->nullable();
            $table->timestampsTz();
            $table->index(['status','preselected_role']);
            $table->index(['preselected_team_id','status']);
        });
        DB::statement("ALTER TABLE allowed_emails ADD CONSTRAINT allowed_emails_role_check CHECK (preselected_role IN ('TEAM_LEADER','EMPLOYEE','QC'))");
        DB::statement("ALTER TABLE allowed_emails ADD CONSTRAINT allowed_emails_qc_scope_check CHECK (preselected_qc_scope IS NULL OR preselected_qc_scope IN ('ASSETS','SOCIAL','CUSTOM'))");
        DB::statement("ALTER TABLE allowed_emails ADD CONSTRAINT allowed_emails_status_check CHECK (status IN ('PENDING','REGISTERED','DISABLED'))");
    }
    public function down(): void { Schema::dropIfExists('allowed_emails'); }
};
