<?php
use App\Enums\TeamStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name',150)->unique();
            $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status',20)->default(TeamStatus::ACTIVE->value);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['status','team_leader_id']);
        });
        DB::statement("ALTER TABLE teams ADD CONSTRAINT teams_status_check CHECK (status IN ('ACTIVE','INACTIVE'))");
    }
    public function down(): void { Schema::dropIfExists('teams'); }
};
