<?php

use App\Enums\ImprovementSessionEventType;
use App\Enums\ImprovementSessionEventVisibility;
use App\Enums\ImprovementSessionStatus;
use App\Enums\PerformanceRating;
use App\Enums\RoleName;
use App\Enums\TeamStatus;
use App\Enums\UserStatus;
use App\Models\ImprovementSession;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

function m4b2OfficeSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->admin->assignRole(RoleName::SUPER_ADMIN->value);

    $this->leaderOne = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->leaderOne->assignRole(RoleName::TEAM_LEADER->value);
    $this->leaderTwo = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->leaderTwo->assignRole(RoleName::TEAM_LEADER->value);

    $teamOne = Team::query()->create([
        'name' => 'Access Team One',
        'team_leader_id' => $this->leaderOne->id,
        'status' => TeamStatus::ACTIVE,
        'created_by' => $this->admin->id,
    ]);
    $teamTwo = Team::query()->create([
        'name' => 'Access Team Two',
        'team_leader_id' => $this->leaderTwo->id,
        'status' => TeamStatus::ACTIVE,
        'created_by' => $this->admin->id,
    ]);

    $this->employeeOne = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
        'primary_team_id' => $teamOne->id,
    ]);
    $this->employeeOne->assignRole(RoleName::EMPLOYEE->value);

    $this->employeeTwo = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
        'primary_team_id' => $teamTwo->id,
    ]);
    $this->employeeTwo->assignRole(RoleName::EMPLOYEE->value);

    $this->qc = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->qc->assignRole(RoleName::QC->value);

    $this->session = ImprovementSession::query()->create([
        'employee_id' => $this->employeeOne->id,
        'performance_month' => '2026-09-01',
        'trigger_negative_points' => 20,
        'trigger_bonus_points' => 0,
        'trigger_rating' => PerformanceRating::POOR,
        'trigger_team_id' => $teamOne->id,
        'status' => ImprovementSessionStatus::OPEN,
        'team_leader_notes' => 'INTERNAL SECRET NOTE',
        'employee_visible_notes' => 'VISIBLE COACHING NOTE',
        'opened_at' => now(),
    ]);

    $this->session->events()->create([
        'event_type' => ImprovementSessionEventType::NOTE_ADDED,
        'visibility' => ImprovementSessionEventVisibility::INTERNAL,
        'note' => 'INTERNAL EVENT',
    ]);
    $this->session->events()->create([
        'event_type' => ImprovementSessionEventType::PLAN_UPDATED,
        'visibility' => ImprovementSessionEventVisibility::EMPLOYEE,
        'note' => 'VISIBLE EVENT',
    ]);
});

it('lets an employee see only their own session and hides internal notes and events', function () {
    $this->actingAs($this->employeeOne)
        ->withSession(m4b2OfficeSession($this->employeeOne))
        ->get(route('employee.improvement-sessions.show', $this->session))
        ->assertOk()
        ->assertSee('VISIBLE COACHING NOTE')
        ->assertSee('VISIBLE EVENT')
        ->assertDontSee('INTERNAL SECRET NOTE')
        ->assertDontSee('INTERNAL EVENT');

    $this->actingAs($this->employeeTwo)
        ->withSession(m4b2OfficeSession($this->employeeTwo))
        ->get(route('employee.improvement-sessions.show', $this->session))
        ->assertNotFound();
});

it('limits Team Leaders to their current team while Super Admin can see all sessions', function () {
    $this->actingAs($this->leaderOne)
        ->withSession(m4b2OfficeSession($this->leaderOne))
        ->get(route('team-leader.improvement-sessions.show', $this->session))
        ->assertOk()
        ->assertSee('INTERNAL SECRET NOTE');

    $this->actingAs($this->leaderTwo)
        ->withSession(m4b2OfficeSession($this->leaderTwo))
        ->get(route('team-leader.improvement-sessions.show', $this->session))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->withSession(m4b2OfficeSession($this->admin))
        ->get(route('admin.improvement-sessions.show', $this->session))
        ->assertOk()
        ->assertSee('INTERNAL SECRET NOTE');
});

it('keeps QC outside Improvement Session routes', function () {
    $this->actingAs($this->qc)
        ->withSession(m4b2OfficeSession($this->qc))
        ->get('/qc/improvement-sessions')
        ->assertNotFound();
});
