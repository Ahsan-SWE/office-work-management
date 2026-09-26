<?php
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_user_id',255)->nullable()->unique();
            $table->foreignId('primary_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('status',20)->default(UserStatus::ACTIVE->value);
            $table->unsignedInteger('session_version')->default(1);
            $table->timestampTz('registered_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->index(['status','primary_team_id']);
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('ACTIVE','INACTIVE','LEFT_COMPANY'))");
    }
    public function down(): void {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['primary_team_id']);
            $table->dropIndex(['status','primary_team_id']);
            $table->dropUnique(['google_user_id']);
            $table->dropColumn(['google_user_id','primary_team_id','status','session_version','registered_at','last_login_at']);
        });
    }
};
