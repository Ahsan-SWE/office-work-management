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

    $this->leader = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $this->leader->assignRole(RoleName::TEAM_LEADER->value);

    $this->team = Team::query()->create([
        'name' => 'Team Alpha',
        'team_leader_id' => $this->leader->id,
        'status' => 'ACTIVE',
    ]);

    $this->leader->update(['primary_team_id' => $this->team->id]);
});

function tlSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('lets a team leader manage an employee in their own team', function () {
    $employee = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'primary_team_id' => $this->team->id,
    ]);
    $employee->assignRole(RoleName::EMPLOYEE->value);

    $this->actingAs($this->leader)
        ->withSession(tlSession($this->leader))
        ->get(route('team-leader.members.show', $employee))
        ->assertOk();

    $this->actingAs($this->leader)
        ->withSession(tlSession($this->leader))
        ->put(route('team-leader.members.capabilities', $employee), [
            'capabilities' => ['ASSETS', 'CUSTOM'],
        ])
        ->assertRedirect();

    expect($employee->capabilities()->pluck('capability')->map(fn ($v) => $v->value)->all())
        ->toEqualCanonicalizing(['ASSETS', 'CUSTOM']);
});

it('blocks a team leader from managing another teams employee', function () {
    $otherTeam = Team::query()->create([
        'name' => 'Team Beta',
        'status' => 'ACTIVE',
    ]);

    $employee = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'primary_team_id' => $otherTeam->id,
    ]);
    $employee->assignRole(RoleName::EMPLOYEE->value);

    $this->actingAs($this->leader)
        ->withSession(tlSession($this->leader))
        ->get(route('team-leader.members.show', $employee))
        ->assertNotFound();
});
