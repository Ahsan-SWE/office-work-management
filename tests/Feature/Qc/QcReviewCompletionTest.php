<?php

use App\Enums\AssignmentStatus;
use App\Enums\ClientStatus;
use App\Enums\Priority;
use App\Enums\QcReasonType;
use App\Enums\QcReviewResult;
use App\Enums\QcScope;
use App\Enums\QcSubmissionStatus;
use App\Enums\QcSubmissionType;
use App\Enums\RoleName;
use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkType;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\QcReason;
use App\Models\QcSubmission;
use App\Models\QcUserScope;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\M2ConfigurationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(M2ConfigurationSeeder::class);

    $this->leader = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->leader->assignRole(RoleName::TEAM_LEADER->value);

    $this->employee = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->employee->assignRole(RoleName::EMPLOYEE->value);

    $this->qc = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->qc->assignRole(RoleName::QC->value);

    QcUserScope::query()->create([
        'user_id' => $this->qc->id,
        'scope' => QcScope::ASSETS,
        'granted_at' => now(),
    ]);

    $client = Client::query()->create([
        'client_code' => 'CL-000001',
        'name' => 'Review Client',
        'normalized_name' => 'review client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/review/edit',
        'status' => ClientStatus::ACTIVE,
        'created_by' => $this->leader->id,
        'updated_by' => $this->leader->id,
    ]);

    $this->work = WorkOrder::query()->create([
        'work_code' => 'AW-000001',
        'client_id' => $client->id,
        'work_type' => WorkType::ASSETS,
        'status' => WorkOrderStatus::QC_IN_PROGRESS,
        'priority' => Priority::HIGH,
        'sheet_url_snapshot' => $client->google_sheet_url,
        'created_by' => $this->leader->id,
    ]);

    $this->assignment = Assignment::query()->create([
        'assignment_code' => 'ASN-000001',
        'work_order_id' => $this->work->id,
        'employee_id' => $this->employee->id,
        'scope_type' => ScopeType::FULL_SECTION,
        'assigned_count' => 20,
        'completed_count' => 20,
        'priority' => Priority::HIGH,
        'status' => AssignmentStatus::SUBMITTED_QC,
        'assigned_by' => $this->leader->id,
        'assigned_at' => now(),
        'started_at' => now(),
    ]);

    $this->submission = QcSubmission::query()->create([
        'submission_code' => 'QCS-000001',
        'idempotency_key' => (string) Str::uuid(),
        'assignment_id' => $this->assignment->id,
        'submitted_by' => $this->employee->id,
        'submission_type' => QcSubmissionType::FINAL,
        'submitted_count' => 20,
        'status' => QcSubmissionStatus::WAITING,
        'submitted_at' => now(),
    ]);
});

function m3ReviewSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('supports mixed approve and rework with a zero-point rework reason', function () {
    $reason = QcReason::query()
        ->where('type', QcReasonType::NEGATIVE->value)
        ->firstOrFail();

    $this->actingAs($this->qc)
        ->withSession(m3ReviewSession($this->qc))
        ->post(route('qc.submissions.start', $this->submission))
        ->assertRedirect();

    $this->actingAs($this->qc)
        ->withSession(m3ReviewSession($this->qc))
        ->post(route('qc.submissions.finish', $this->submission), [
            'approved_count' => 18,
            'rework_count' => 2,
            'review_comment' => 'Fix the returned items.',
            'issues' => [
                [
                    'reason_id' => $reason->id,
                    'negative_points' => 0,
                    'comment' => 'Correction required, no negative mark.',
                ],
            ],
        ])
        ->assertRedirect();

    $review = $this->submission->review()->firstOrFail();

    expect($review->result)->toBe(QcReviewResult::REWORK_REQUIRED)
        ->and($review->approved_count)->toBe(18)
        ->and($review->rework_count)->toBe(2)
        ->and($review->negative_points)->toBe(0)
        ->and($this->assignment->fresh()->status)->toBe(AssignmentStatus::REWORK)
        ->and($this->work->fresh()->status)->toBe(WorkOrderStatus::REWORK);
});

it('rejects a review when total negative points exceed five', function () {
    $reasons = QcReason::query()
        ->where('type', QcReasonType::NEGATIVE->value)
        ->take(2)
        ->get();

    $this->actingAs($this->qc)
        ->withSession(m3ReviewSession($this->qc))
        ->post(route('qc.submissions.start', $this->submission))
        ->assertRedirect();

    $this->actingAs($this->qc)
        ->withSession(m3ReviewSession($this->qc))
        ->post(route('qc.submissions.finish', $this->submission), [
            'approved_count' => 18,
            'rework_count' => 2,
            'issues' => [
                ['reason_id' => $reasons[0]->id, 'negative_points' => 3],
                ['reason_id' => $reasons[1]->id, 'negative_points' => 3],
            ],
        ])
        ->assertSessionHasErrors('qc');

    expect($this->submission->fresh()->status)->toBe(QcSubmissionStatus::REVIEWING)
        ->and($this->submission->review()->firstOrFail()->reviewed_at)->toBeNull();
});

it('rejects a review when total bonus points exceed five', function () {
    $reasons = QcReason::query()
        ->where('type', QcReasonType::BONUS->value)
        ->take(2)
        ->get();

    $this->actingAs($this->qc)
        ->withSession(m3ReviewSession($this->qc))
        ->post(route('qc.submissions.start', $this->submission))
        ->assertRedirect();

    $this->actingAs($this->qc)
        ->withSession(m3ReviewSession($this->qc))
        ->post(route('qc.submissions.finish', $this->submission), [
            'approved_count' => 20,
            'rework_count' => 0,
            'bonus_items' => [
                ['reason_id' => $reasons[0]->id, 'points' => 3],
                ['reason_id' => $reasons[1]->id, 'points' => 3],
            ],
        ])
        ->assertSessionHasErrors('qc');

    expect($this->submission->fresh()->status)->toBe(QcSubmissionStatus::REVIEWING)
        ->and($this->submission->review()->firstOrFail()->reviewed_at)->toBeNull();
});

