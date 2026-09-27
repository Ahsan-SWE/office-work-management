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
use App\Models\QcReviewEscalation;
use App\Models\QcReviewOverride;
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
        'name' => 'Override Performance Team',
        'team_leader_id' => $this->leader->id,
        'status' => TeamStatus::ACTIVE,
        'created_by' => $this->admin->id,
    ]);
    $this->employee->update(['primary_team_id' => $team->id]);

    $client = Client::query()->create([
        'client_code' => 'CL-000001',
        'name' => 'Override Performance Client',
        'normalized_name' => 'override performance client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/override-performance/edit',
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
    $assignment = Assignment::query()->create([
        'assignment_code' => 'ASN-000001',
        'work_order_id' => $work->id,
        'employee_id' => $this->employee->id,
        'scope_type' => ScopeType::FULL_SECTION,
        'assigned_count' => 1,
        'completed_count' => 1,
        'priority' => Priority::NORMAL,
        'status' => AssignmentStatus::SUBMITTED_QC,
        'assigned_by' => $this->leader->id,
        'assigned_at' => now(),
        'started_at' => now(),
    ]);
    $submission = QcSubmission::query()->create([
        'submission_code' => 'QCS-000001',
        'idempotency_key' => (string) Str::uuid(),
        'assignment_id' => $assignment->id,
        'submitted_by' => $this->employee->id,
        'submission_type' => QcSubmissionType::FINAL,
        'submitted_count' => 1,
        'status' => QcSubmissionStatus::REVIEWED,
        'submitted_at' => CarbonImmutable::parse('2026-09-12 10:00:00', 'UTC'),
    ]);
    $this->review = QcReview::query()->create([
        'review_code' => 'QC-000001',
        'qc_submission_id' => $submission->id,
        'reviewer_id' => $this->qc->id,
        'responsible_employee_id' => $this->employee->id,
        'approved_count' => 1,
        'rework_count' => 0,
        'result' => QcReviewResult::APPROVED,
        'bonus_points' => 0,
        'negative_points' => 5,
        'is_major_error' => false,
        'started_at' => now()->subHour(),
        'reviewed_at' => now(),
    ]);
    $this->escalation = QcReviewEscalation::query()->create([
        'qc_review_id' => $this->review->id,
        'raised_by' => $this->leader->id,
        'reason' => 'Please review the quality mark.',
        'status' => 'OPEN',
        'opened_at' => now(),
    ]);

    app(MonthlyQualityPerformanceService::class)->recalculateForReview($this->review);
});

it('retroactively applies the latest Super Admin override without mutating the original QC review', function () {
    $before = EmployeeMonthlyQualityPerformance::query()->firstOrFail();
    expect($before->negative_points)->toBe(5);

    $session = [
        'office_session_version' => $this->admin->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];

    $this->actingAs($this->admin)
        ->withSession($session)
        ->post(route('admin.qc-escalations.override', $this->escalation), [
            'approved_count' => 1,
            'rework_count' => 0,
            'negative_points' => 0,
            'bonus_points' => 2,
            'is_major_error' => 0,
            'reason' => 'Evidence supports removing the original negative mark.',
        ])
        ->assertRedirect();

    $original = $this->review->fresh();
    $override = QcReviewOverride::query()->firstOrFail();
    $after = EmployeeMonthlyQualityPerformance::query()->firstOrFail();

    expect($original->negative_points)->toBe(5)
        ->and($override->negative_points)->toBe(0)
        ->and($after->negative_points)->toBe(0)
        ->and($after->bonus_points)->toBe(2)
        ->and($after->rating)->toBe(PerformanceRating::GOOD);
});
