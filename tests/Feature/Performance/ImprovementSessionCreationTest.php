<?php

use App\Enums\ImprovementSessionEventType;
use App\Enums\ImprovementSessionStatus;
use App\Enums\PerformanceRating;
use App\Enums\RoleName;
use App\Enums\TeamStatus;
use App\Enums\UserStatus;
use App\Models\EmployeeMonthlyQualityPerformance;
use App\Models\ImprovementSession;
use App\Models\Team;
use App\Models\User;
use App\Services\Performance\ImprovementSessionService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $this->admin->assignRole(RoleName::SUPER_ADMIN->value);

    $this->leader = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $this->leader->assignRole(RoleName::TEAM_LEADER->value);

    $this->employee = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $this->employee->assignRole(RoleName::EMPLOYEE->value);

    $team = Team::query()->create([
        'name' => 'Improvement Team',
        'team_leader_id' => $this->leader->id,
        'status' => TeamStatus::ACTIVE,
        'created_by' => $this->admin->id,
    ]);

    $this->employee->update(['primary_team_id' => $team->id]);
    $this->service = app(ImprovementSessionService::class);
});

it('creates exactly one open session when a month becomes Poor and does not let bonus offset the trigger', function () {
    EmployeeMonthlyQualityPerformance::query()->create([
        'employee_id' => $this->employee->id,
        'performance_month' => '2026-09-01',
        'review_count' => 5,
        'negative_points' => 20,
        'bonus_points' => 50,
        'rating' => PerformanceRating::POOR,
        'calculated_at' => now(),
    ]);

    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-09-01'));
    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-09-01'));

    $session = ImprovementSession::query()->firstOrFail();

    expect(ImprovementSession::query()->count())->toBe(1)
        ->and($session->status)->toBe(ImprovementSessionStatus::OPEN)
        ->and($session->trigger_negative_points)->toBe(20)
        ->and($session->trigger_bonus_points)->toBe(50)
        ->and($session->events()->where('event_type', ImprovementSessionEventType::CREATED_FROM_POOR->value)->count())->toBe(1);
});

it('does not create a session for Needs Attention', function () {
    EmployeeMonthlyQualityPerformance::query()->create([
        'employee_id' => $this->employee->id,
        'performance_month' => '2026-09-01',
        'review_count' => 5,
        'negative_points' => 19,
        'bonus_points' => 0,
        'rating' => PerformanceRating::NEEDS_ATTENTION,
        'calculated_at' => now(),
    ]);

    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-09-01'));

    expect(ImprovementSession::query()->count())->toBe(0);
});
