<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('permission_key',100);
            $table->string('decision',10);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['user_id','permission_key']);
            $table->index(['permission_key','decision']);
        });
        DB::statement("ALTER TABLE user_permission_overrides ADD CONSTRAINT user_permission_overrides_decision_check CHECK (decision IN ('ALLOW','DENY'))");
    }
    public function down(): void { Schema::dropIfExists('user_permission_overrides'); }
};
