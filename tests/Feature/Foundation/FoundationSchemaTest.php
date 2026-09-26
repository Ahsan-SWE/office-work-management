<?php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the milestone one foundation tables', function () {
    expect(Schema::hasTable('teams'))->toBeTrue()
        ->and(Schema::hasTable('allowed_emails'))->toBeTrue()
        ->and(Schema::hasTable('team_memberships'))->toBeTrue()
        ->and(Schema::hasTable('user_capabilities'))->toBeTrue()
        ->and(Schema::hasTable('qc_user_scopes'))->toBeTrue()
        ->and(Schema::hasTable('user_permission_overrides'))->toBeTrue();

    expect(Schema::hasColumns('users', [
        'google_user_id','primary_team_id','status','session_version','registered_at','last_login_at',
    ]))->toBeTrue();
});
