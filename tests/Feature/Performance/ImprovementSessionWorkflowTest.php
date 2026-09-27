<?php

use App\Enums\ImprovementSessionEventType;
use App\Enums\ImprovementSessionStatus;
use App\Enums\PerformanceRating;
use App\Enums\RoleName;
use App\Enums\TeamStatus;
use App\Enums\UserStatus;
use App\Models\ImprovementSession;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->admin->assignRole(RoleName::SUPER_ADMIN->value);

    $this->leader = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->leader->assignRole(RoleName::TEAM_LEADER->value);

    $this->employee = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->employee->assignRole(RoleName::EMPLOYEE->value);

    $team = Team::query()->create([
        'name' => 'Workflow Team',
        'team_leader_id' => $this->leader->id,
        'status' => TeamStatus::ACTIVE,
        'created_by' => $this->admin->id,
    ]);
    $this->employee->update(['primary_team_id' => $team->id]);

    $this->session = ImprovementSession::query()->create([
        'employee_id' => $this->employee->id,
        'performance_month' => CarbonImmutable::parse('2026-09-01'),
        'trigger_negative_points' => 20,
        'trigger_bonus_points' => 2,
        'trigger_rating' => PerformanceRating::POOR,
        'trigger_team_id' => $team->id,
        'status' => ImprovementSessionStatus::OPEN,
        'opened_at' => now(),
    ]);

    $this->officeSession = [
        'office_session_version' => $this->leader->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
});

it('supports OPEN to IN_PROGRESS to COMPLETED with durable events and notes', function () {
    $this->actingAs($this->leader)
        ->withSession($this->officeSession)
        ->post(route('team-leader.improvement-sessions.start', $this->session))
        ->assertRedirect();

    $this->actingAs($this->leader)
        ->withSession($this->officeSession)
        ->put(route('team-leader.improvement-sessions.update', $this->session), [
            'improvement_plan' => 'Review quality checklist before submission.',
            'team_leader_notes' => 'Internal coaching note.',
            'employee_visible_notes' => 'Please use the checklist on every task.',
            'follow_up_date' => '2026-10-15',
        ])
        ->assertRedirect();

    $this->actingAs($this->leader)
        ->withSession($this->officeSession)
        ->post(route('team-leader.improvement-sessions.complete', $this->session))
        ->assertRedirect();

    $this->session->refresh();

    expect($this->session->status)->toBe(ImprovementSessionStatus::COMPLETED)
        ->and($this->session->improvement_plan)->toContain('checklist')
        ->and($this->session->team_leader_notes)->toBe('Internal coaching note.')
        ->and($this->session->events()->where('event_type', ImprovementSessionEventType::STARTED->value)->count())->toBe(1)
        ->and($this->session->events()->where('event_type', ImprovementSessionEventType::COMPLETED->value)->count())->toBe(1);
});

it('keeps a completed session read-only from normal management updates', function () {
    $this->session->update([
        'status' => ImprovementSessionStatus::COMPLETED,
        'completed_at' => now(),
    ]);

    $this->actingAs($this->leader)
        ->withSession($this->officeSession)
        ->put(route('team-leader.improvement-sessions.update', $this->session), [
            'improvement_plan' => 'Should not persist.',
        ])
        ->assertSessionHasErrors('session');

    expect($this->session->fresh()->improvement_plan)->toBeNull();
});
