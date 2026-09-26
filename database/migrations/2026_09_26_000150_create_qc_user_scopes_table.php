<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('qc_user_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('scope',20);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('granted_at')->useCurrent();
            $table->timestampTz('revoked_at')->nullable();
            $table->index(['user_id','revoked_at']);
        });
        DB::statement("ALTER TABLE qc_user_scopes ADD CONSTRAINT qc_user_scopes_scope_check CHECK (scope IN ('ASSETS','SOCIAL','CUSTOM'))");
        DB::statement("CREATE UNIQUE INDEX qc_user_scopes_one_active_scope ON qc_user_scopes (user_id, scope) WHERE revoked_at IS NULL");
    }
    public function down(): void { Schema::dropIfExists('qc_user_scopes'); }
};
