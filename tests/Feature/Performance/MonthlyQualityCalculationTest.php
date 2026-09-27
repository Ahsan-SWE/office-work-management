<?php

use App\Enums\AssignmentStatus;
use App\Enums\ClientStatus;
use App\Enums\PerformanceRating;
use App\Enums\Priority;
use App\Enums\QcReviewResult;
use App\Enums\QcSubmissionStatus;
use App\Enums\QcSubmissionType;
use App\Enums\RoleName;
use App\Enums\ScopeType;
use App\Enums\TeamStatus;
use App\Enums\UserStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkType;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\EmployeeMonthlyQualityPerformance;
use App\Models\QcReview;
use App\Models\QcSubmission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Performance\MonthlyQualityPerformanceService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->admin->assignRole(RoleName::SUPER_ADMIN->value);

    $this->leader = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->leader->assignRole(RoleName::TEAM_LEADER->value);

    $this->employee = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->employee->assignRole(RoleName::EMPLOYEE->value);

    $this->qc = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->qc->assignRole(RoleName::QC->value);

    $team = Team::query()->create([
        'name' => 'Performance Team',
        'team_leader_id' => $this->leader->id,
        'status' => TeamStatus::ACTIVE,
        'created_by' => $this->admin->id,
    ]);
    $this->employee->update(['primary_team_id' => $team->id]);

    $client = Client::query()->create([
        'client_code' => 'CL-000001',
        'name' => 'Performance Client',
        'normalized_name' => 'performance client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/performance/edit',
        'status' => ClientStatus::ACTIVE,
        'created_by' => $this->leader->id,
        'updated_by' => $this->leader->id,
    ]);

    $work = WorkOrder::query()->create([
        'work_code' => 'AW-000001',
        'client_id' => $client->id,
        'work_type' => WorkType::ASSETS,
        'status' => WorkOrderStatus::QC_IN_PROGRESS,
        'priority' => Priority::NORMAL,
        'sheet_url_snapshot' => $client->google_sheet_url,
        'created_by' => $this->leader->id,
    ]);

    $this->assignment = Assignment::query()->create([
        'assignment_code' => 'ASN-000001',
        'work_order_id' => $work->id,
        'employee_id' => $this->employee->id,
        'scope_type' => ScopeType::FULL_SECTION,
        'assigned_count' => 20,
        'completed_count' => 20,
        'priority' => Priority::NORMAL,
        'status' => AssignmentStatus::SUBMITTED_QC,
        'assigned_by' => $this->leader->id,
        'assigned_at' => now(),
        'started_at' => now(),
    ]);

    $this->performance = app(MonthlyQualityPerformanceService::class);
});

function m4b1CreateCompletedReview(
    object $test,
    string $submittedAt,
    int $negative,
    int $bonus,
    QcSubmissionType $type = QcSubmissionType::PARTIAL,
    ?QcReview $sourceReview = null,
): QcReview {
    $sequence = QcSubmission::query()->count() + 1;

    $submission = QcSubmission::query()->create([
        'submission_code' => sprintf('QCS-%06d', $sequence),
        'idempotency_key' => (string) Str::uuid(),
        'assignment_id' => $test->assignment->id,
        'submitted_by' => $test->employee->id,
        'submission_type' => $type,
        'submitted_count' => 1,
        'source_review_id' => $sourceReview?->id,
        'status' => QcSubmissionStatus::REVIEWED,
        'submitted_at' => CarbonImmutable::parse($submittedAt, 'UTC'),
    ]);

    return QcReview::query()->create([
        'review_code' => sprintf('QC-%06d', $sequence),
        'qc_submission_id' => $submission->id,
        'reviewer_id' => $test->qc->id,
        'responsible_employee_id' => $test->employee->id,
        'approved_count' => 1,
        'rework_count' => 0,
        'result' => QcReviewResult::APPROVED,
        'bonus_points' => $bonus,
        'negative_points' => $negative,
        'is_major_error' => false,
        'started_at' => CarbonImmutable::parse($submittedAt, 'UTC')->addDay(),
        'reviewed_at' => CarbonImmutable::parse($submittedAt, 'UTC')->addDays(2),
    ]);
}

it('maps monthly negative thresholds without letting bonus offset negative', function () {
    expect($this->performance->ratingFor(0))->toBe(PerformanceRating::GOOD)
        ->and($this->performance->ratingFor(9))->toBe(PerformanceRating::GOOD)
        ->and($this->performance->ratingFor(10))->toBe(PerformanceRating::NEEDS_ATTENTION)
        ->and($this->performance->ratingFor(19))->toBe(PerformanceRating::NEEDS_ATTENTION)
        ->and($this->performance->ratingFor(20))->toBe(PerformanceRating::POOR);

    $first = m4b1CreateCompletedReview($this, '2026-09-03 09:00:00', 6, 5);
    $second = m4b1CreateCompletedReview($this, '2026-09-10 09:00:00', 4, 5);

    $this->performance->recalculateForReview($first);
    $this->performance->recalculateForReview($second);

    $row = EmployeeMonthlyQualityPerformance::query()->firstOrFail();

    expect($row->negative_points)->toBe(10)
        ->and($row->bonus_points)->toBe(10)
        ->and($row->rating)->toBe(PerformanceRating::NEEDS_ATTENTION);
});

it('uses QC submission month and treats a rework resubmission as its own month', function () {
    $augustReview = m4b1CreateCompletedReview(
        $this,
        '2026-08-30 09:00:00',
        4,
        2,
        QcSubmissionType::FINAL,
    );
    $septemberReview = m4b1CreateCompletedReview(
        $this,
        '2026-09-03 09:00:00',
        2,
        1,
        QcSubmissionType::REWORK,
        $augustReview,
    );

    $this->performance->recalculateForReview($augustReview);
    $this->performance->recalculateForReview($septemberReview);

    $august = EmployeeMonthlyQualityPerformance::query()
        ->whereDate('performance_month', '2026-08-01')
        ->firstOrFail();
    $september = EmployeeMonthlyQualityPerformance::query()
        ->whereDate('performance_month', '2026-09-01')
        ->firstOrFail();

    expect($august->review_count)->toBe(1)
        ->and($august->negative_points)->toBe(4)
        ->and($september->review_count)->toBe(1)
        ->and($september->negative_points)->toBe(2);
});

it('stores no projection row when an employee has no completed QC review in the month', function () {
    $this->performance->recalculateEmployeeMonth(
        $this->employee->id,
        CarbonImmutable::parse('2026-10-01', 'UTC'),
    );

    expect(EmployeeMonthlyQualityPerformance::query()->count())->toBe(0);
});
