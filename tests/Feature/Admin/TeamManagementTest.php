<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $this->admin->assignRole(RoleName::SUPER_ADMIN->value);
});

function adminSession(): array
{
    return [
        'office_session_version' => 1,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('creates a team and assigns a primary team leader', function () {
    $leader = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $leader->assignRole(RoleName::TEAM_LEADER->value);

    $response = $this
        ->actingAs($this->admin)
        ->withSession(adminSession())
        ->post(route('admin.teams.store'), [
            'name' => 'Team Alpha',
            'team_leader_id' => $leader->id,
        ]);

    $response->assertRedirect();

    $team = Team::query()->where('name', 'Team Alpha')->firstOrFail();

    expect($team->team_leader_id)->toBe($leader->id)
        ->and($leader->fresh()->primary_team_id)->toBe($team->id);

    $this->assertDatabaseHas('team_memberships', [
        'team_id' => $team->id,
        'user_id' => $leader->id,
        'is_primary' => true,
    ]);
});

it('prevents one team leader from leading two active teams', function () {
    $leader = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $leader->assignRole(RoleName::TEAM_LEADER->value);

    Team::query()->create([
        'name' => 'Team One',
        'team_leader_id' => $leader->id,
        'status' => 'ACTIVE',
        'created_by' => $this->admin->id,
    ]);

    $response = $this
        ->actingAs($this->admin)
        ->withSession(adminSession())
        ->from(route('admin.teams.create'))
        ->post(route('admin.teams.store'), [
            'name' => 'Team Two',
            'team_leader_id' => $leader->id,
        ]);

    $response->assertRedirect(route('admin.teams.create'));
    $response->assertSessionHasErrors('team_leader_id');
});
