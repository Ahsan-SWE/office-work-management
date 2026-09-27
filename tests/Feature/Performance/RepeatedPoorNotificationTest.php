<?php

use App\Enums\ImprovementSessionStatus;
use App\Enums\PerformanceRating;
use App\Enums\RoleName;
use App\Enums\TeamStatus;
use App\Enums\UserStatus;
use App\Models\EmployeeMonthlyQualityPerformance;
use App\Models\ImprovementSession;
use App\Models\OfficeNotification;
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
        'name' => 'Repeated Poor Team',
        'team_leader_id' => $this->leader->id,
        'status' => TeamStatus::ACTIVE,
        'created_by' => $this->admin->id,
    ]);
    $this->employee->update(['primary_team_id' => $team->id]);

    $this->service = app(ImprovementSessionService::class);
});

function m4b2PoorPerformance(int $employeeId, string $month, int $negative = 20): EmployeeMonthlyQualityPerformance
{
    return EmployeeMonthlyQualityPerformance::query()->create([
        'employee_id' => $employeeId,
        'performance_month' => $month,
        'review_count' => 4,
        'negative_points' => $negative,
        'bonus_points' => 0,
        'rating' => PerformanceRating::POOR,
        'calculated_at' => now(),
    ]);
}

it('alerts active Super Admin once for two consecutive Poor calendar months', function () {
    m4b2PoorPerformance($this->employee->id, '2026-09-01');
    m4b2PoorPerformance($this->employee->id, '2026-10-01');

    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-09-01'));
    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-10-01'));
    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-10-01'));

    $october = ImprovementSession::query()
        ->whereDate('performance_month', '2026-10-01')
        ->firstOrFail();

    expect($october->status)->toBe(ImprovementSessionStatus::OPEN)
        ->and($october->repeated_poor_alerted_at)->not->toBeNull()
        ->and(OfficeNotification::query()->where('type', 'REPEATED_POOR')->count())->toBe(1);
});

it('does not treat a missing or non-Poor middle month as consecutive Poor', function () {
    m4b2PoorPerformance($this->employee->id, '2026-09-01');
    m4b2PoorPerformance($this->employee->id, '2026-11-01');

    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-09-01'));
    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-11-01'));

    expect(OfficeNotification::query()->where('type', 'REPEATED_POOR')->count())->toBe(0);
});

it('re-evaluates the next month when the previous month later becomes Poor', function () {
    m4b2PoorPerformance($this->employee->id, '2026-10-01');

    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-10-01'));

    expect(OfficeNotification::query()->count())->toBe(0);

    m4b2PoorPerformance($this->employee->id, '2026-09-01');
    $this->service->reconcileEmployeeMonth($this->employee->id, CarbonImmutable::parse('2026-09-01'));

    expect(OfficeNotification::query()->where('type', 'REPEATED_POOR')->count())->toBe(1);
});
