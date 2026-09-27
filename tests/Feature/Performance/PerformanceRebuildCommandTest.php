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
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

it('rebuilds the projection idempotently from completed QC reviews', function () {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $admin->assignRole(RoleName::SUPER_ADMIN->value);
    $leader = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $leader->assignRole(RoleName::TEAM_LEADER->value);
    $employee = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $employee->assignRole(RoleName::EMPLOYEE->value);
    $qc = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $qc->assignRole(RoleName::QC->value);

    $team = Team::query()->create([
        'name' => 'Rebuild Team',
        'team_leader_id' => $leader->id,
        'status' => TeamStatus::ACTIVE,
        'created_by' => $admin->id,
    ]);
    $employee->update(['primary_team_id' => $team->id]);

    $client = Client::query()->create([
        'client_code' => 'CL-000001',
        'name' => 'Rebuild Client',
        'normalized_name' => 'rebuild client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/rebuild/edit',
        'status' => ClientStatus::ACTIVE,
        'created_by' => $leader->id,
        'updated_by' => $leader->id,
    ]);
    $work = WorkOrder::query()->create([
        'work_code' => 'AW-000001',
        'client_id' => $client->id,
        'work_type' => WorkType::ASSETS,
        'status' => WorkOrderStatus::QC_IN_PROGRESS,
        'priority' => Priority::NORMAL,
        'sheet_url_snapshot' => $client->google_sheet_url,
        'created_by' => $leader->id,
    ]);
    $assignment = Assignment::query()->create([
        'assignment_code' => 'ASN-000001',
        'work_order_id' => $work->id,
        'employee_id' => $employee->id,
        'scope_type' => ScopeType::FULL_SECTION,
        'assigned_count' => 1,
        'completed_count' => 1,
        'priority' => Priority::NORMAL,
        'status' => AssignmentStatus::SUBMITTED_QC,
        'assigned_by' => $leader->id,
        'assigned_at' => now(),
        'started_at' => now(),
    ]);
    $submission = QcSubmission::query()->create([
        'submission_code' => 'QCS-000001',
        'idempotency_key' => (string) Str::uuid(),
        'assignment_id' => $assignment->id,
        'submitted_by' => $employee->id,
        'submission_type' => QcSubmissionType::FINAL,
        'submitted_count' => 1,
        'status' => QcSubmissionStatus::REVIEWED,
        'submitted_at' => CarbonImmutable::parse('2026-09-20 10:00:00', 'UTC'),
    ]);
    QcReview::query()->create([
        'review_code' => 'QC-000001',
        'qc_submission_id' => $submission->id,
        'reviewer_id' => $qc->id,
        'responsible_employee_id' => $employee->id,
        'approved_count' => 1,
        'rework_count' => 0,
        'result' => QcReviewResult::APPROVED,
        'bonus_points' => 2,
        'negative_points' => 10,
        'is_major_error' => false,
        'started_at' => now()->subHour(),
        'reviewed_at' => now(),
    ]);

    EmployeeMonthlyQualityPerformance::query()->create([
        'employee_id' => $employee->id,
        'performance_month' => '2026-09-01',
        'review_count' => 99,
        'negative_points' => 99,
        'bonus_points' => 99,
        'rating' => PerformanceRating::POOR,
        'calculated_at' => now(),
    ]);

    expect(Artisan::call('performance:rebuild-quality'))->toBe(0);

    $row = EmployeeMonthlyQualityPerformance::query()->firstOrFail();
    expect($row->review_count)->toBe(1)
        ->and($row->negative_points)->toBe(10)
        ->and($row->bonus_points)->toBe(2)
        ->and($row->rating)->toBe(PerformanceRating::NEEDS_ATTENTION);

    expect(Artisan::call('performance:rebuild-quality'))->toBe(0)
        ->and(EmployeeMonthlyQualityPerformance::query()->count())->toBe(1);
});
