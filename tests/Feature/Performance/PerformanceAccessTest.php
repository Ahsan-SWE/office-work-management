<?php

use App\Enums\PerformanceRating;
use App\Enums\PermissionDecision;
use App\Enums\RoleName;
use App\Enums\TeamStatus;
use App\Enums\UserStatus;
use App\Models\EmployeeMonthlyQualityPerformance;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->admin->assignRole(RoleName::SUPER_ADMIN->value);

    $this->leaderOne = User::factory()->create([
        'name' => 'Leader One',
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $this->leaderOne->assignRole(RoleName::TEAM_LEADER->value);
    $this->leaderTwo = User::factory()->create([
        'name' => 'Leader Two',
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $this->leaderTwo->assignRole(RoleName::TEAM_LEADER->value);

    $teamOne = Team::query()->create([
        'name' => 'Team One',
        'team_leader_id' => $this->leaderOne->id,
        'status' => TeamStatus::ACTIVE,
        'created_by' => $this->admin->id,
    ]);
    $teamTwo = Team::query()->create([
        'name' => 'Team Two',
        'team_leader_id' => $this->leaderTwo->id,
        'status' => TeamStatus::ACTIVE,
        'created_by' => $this->admin->id,
    ]);

    $this->employeeOne = User::factory()->create([
        'name' => 'Employee Alpha',
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
        'primary_team_id' => $teamOne->id,
    ]);
    $this->employeeOne->assignRole(RoleName::EMPLOYEE->value);

    $this->employeeTwo = User::factory()->create([
        'name' => 'Employee Beta',
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
        'primary_team_id' => $teamTwo->id,
    ]);
    $this->employeeTwo->assignRole(RoleName::EMPLOYEE->value);

    $this->qc = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->qc->assignRole(RoleName::QC->value);

    EmployeeMonthlyQualityPerformance::query()->create([
        'employee_id' => $this->employeeOne->id,
        'performance_month' => '2026-09-01',
        'review_count' => 4,
        'negative_points' => 12,
        'bonus_points' => 3,
        'rating' => PerformanceRating::NEEDS_ATTENTION,
        'calculated_at' => now(),
    ]);
    EmployeeMonthlyQualityPerformance::query()->create([
        'employee_id' => $this->employeeTwo->id,
        'performance_month' => '2026-09-01',
        'review_count' => 6,
        'negative_points' => 20,
        'bonus_points' => 5,
        'rating' => PerformanceRating::POOR,
        'calculated_at' => now(),
    ]);
});

function m4b1PerformanceSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('lets an employee see only their own monthly quality performance', function () {
    $this->actingAs($this->employeeOne)
        ->withSession(m4b1PerformanceSession($this->employeeOne))
        ->get(route('employee.performance.index', ['month' => '2026-09']))
        ->assertOk()
        ->assertSee('Needs Attention')
        ->assertSee('12')
        ->assertDontSee('Employee Beta');
});

it('lets a Team Leader see only employees in their current team', function () {
    $this->actingAs($this->leaderOne)
        ->withSession(m4b1PerformanceSession($this->leaderOne))
        ->get(route('team-leader.performance.index', ['month' => '2026-09']))
        ->assertOk()
        ->assertSee('Employee Alpha')
        ->assertDontSee('Employee Beta');
});

it('lets Super Admin see all employees and keeps QC outside monthly performance routes', function () {
    $this->actingAs($this->admin)
        ->withSession(m4b1PerformanceSession($this->admin))
        ->get(route('admin.performance.index', ['month' => '2026-09']))
        ->assertOk()
        ->assertSee('Employee Alpha')
        ->assertSee('Employee Beta');

    $this->actingAs($this->qc)
        ->withSession(m4b1PerformanceSession($this->qc))
        ->get('/qc/performance')
        ->assertNotFound();
});

it('honors an explicit DENY override for employee performance access', function () {
    UserPermissionOverride::query()->create([
        'user_id' => $this->employeeOne->id,
        'permission_key' => 'performance.view_own',
        'decision' => PermissionDecision::DENY,
        'changed_by' => $this->admin->id,
    ]);

    $this->actingAs($this->employeeOne)
        ->withSession(m4b1PerformanceSession($this->employeeOne))
        ->get(route('employee.performance.index', ['month' => '2026-09']))
        ->assertForbidden();
});
