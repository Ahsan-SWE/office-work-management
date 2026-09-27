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
        'name' => 'Recalc Team',
        'team_leader_id' => $this->leader->id,
        'status' => TeamStatus::ACTIVE,
        'created_by' => $this->admin->id,
    ]);
    $this->employee->update(['primary_team_id' => $team->id]);

    $this->performance = EmployeeMonthlyQualityPerformance::query()->create([
        'employee_id' => $this->employee->id,
        'performance_month' => '2026-09-01',
        'review_count' => 6,
        'negative_points' => 22,
        'bonus_points' => 3,
        'rating' => PerformanceRating::POOR,
        'calculated_at' => now(),
    ]);

    $this->service = app(ImprovementSessionService::class);
    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-09-01'));
});

it('cancels without deleting when recalculation removes Poor', function () {
    $session = ImprovementSession::query()->firstOrFail();
    $session->update([
        'improvement_plan' => 'Preserve this plan.',
        'team_leader_notes' => 'Internal note.',
    ]);

    $this->performance->update([
        'negative_points' => 15,
        'rating' => PerformanceRating::NEEDS_ATTENTION,
    ]);

    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-09-01'));

    $session->refresh();

    expect($session->status)->toBe(ImprovementSessionStatus::CANCELLED_BY_RECALCULATION)
        ->and($session->improvement_plan)->toBe('Preserve this plan.')
        ->and($session->events()->where('event_type', ImprovementSessionEventType::CANCELLED_BY_RECALCULATION->value)->count())->toBe(1);
});

it('reopens the same session row if the same month becomes Poor again', function () {
    $session = ImprovementSession::query()->firstOrFail();
    $originalId = $session->id;
    $session->update(['employee_visible_notes' => 'Keep this history.']);

    $this->performance->update([
        'negative_points' => 15,
        'rating' => PerformanceRating::NEEDS_ATTENTION,
    ]);
    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-09-01'));

    $this->performance->update([
        'negative_points' => 21,
        'rating' => PerformanceRating::POOR,
    ]);
    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-09-01'));

    $session->refresh();

    expect(ImprovementSession::query()->count())->toBe(1)
        ->and($session->id)->toBe($originalId)
        ->and($session->status)->toBe(ImprovementSessionStatus::OPEN)
        ->and($session->employee_visible_notes)->toBe('Keep this history.')
        ->and($session->events()->where('event_type', ImprovementSessionEventType::REOPENED_BY_RECALCULATION->value)->count())->toBe(1);
});
