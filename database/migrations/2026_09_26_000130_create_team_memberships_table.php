<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('team_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['user_id','ended_at']);
            $table->index(['team_id','ended_at']);
        });
        DB::statement("CREATE UNIQUE INDEX team_memberships_one_active_primary_per_user ON team_memberships (user_id) WHERE ended_at IS NULL AND is_primary = true");
    }
    public function down(): void { Schema::dropIfExists('team_memberships'); }
};
