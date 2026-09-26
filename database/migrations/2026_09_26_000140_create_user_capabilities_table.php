<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('capability',20);
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['user_id','capability']);
        });
        DB::statement("ALTER TABLE user_capabilities ADD CONSTRAINT user_capabilities_check CHECK (capability IN ('ASSETS','SOCIAL','CUSTOM'))");
    }
    public function down(): void { Schema::dropIfExists('user_capabilities'); }
};
