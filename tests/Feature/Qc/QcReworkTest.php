<?php

use App\Enums\AssignmentStatus;
use App\Enums\ClientStatus;
use App\Enums\Priority;
use App\Enums\QcReasonType;
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
        'name' => 'Rework Client',
        'normalized_name' => 'rework client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/rework/edit',
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

    $this->original = QcSubmission::query()->create([
        'submission_code' => 'QCS-000001',
        'idempotency_key' => (string) Str::uuid(),
        'assignment_id' => $this->assignment->id,
        'submitted_by' => $this->employee->id,
        'submission_type' => QcSubmissionType::FINAL,
        'submitted_count' => 20,
        'status' => QcSubmissionStatus::WAITING,
        'submitted_at' => now(),
    ]);

    $reason = QcReason::query()
        ->where('type', QcReasonType::NEGATIVE->value)
        ->firstOrFail();

    $this->actingAs($this->qc)
        ->withSession(m3ReworkSession($this->qc))
        ->post(route('qc.submissions.start', $this->original));

    $this->actingAs($this->qc)
        ->withSession(m3ReworkSession($this->qc))
        ->post(route('qc.submissions.finish', $this->original), [
            'approved_count' => 18,
            'rework_count' => 2,
            'issues' => [
                ['reason_id' => $reason->id, 'negative_points' => 1],
            ],
        ]);

    $this->sourceReview = $this->original->review()->firstOrFail();
});

function m3ReworkSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('requires one full rework resubmission per source review and completes after approval', function () {
    $this->actingAs($this->employee)
        ->withSession(m3ReworkSession($this->employee))
        ->post(route('employee.work.qc-rework', [$this->assignment, $this->sourceReview]), [
            'idempotency_key' => (string) Str::uuid(),
            'employee_note' => 'Both returned items fixed.',
        ])
        ->assertRedirect();

    $reworkSubmission = QcSubmission::query()
        ->where('source_review_id', $this->sourceReview->id)
        ->firstOrFail();

    expect($reworkSubmission->submitted_count)->toBe(2)
        ->and($reworkSubmission->submission_type)->toBe(QcSubmissionType::REWORK)
        ->and($this->assignment->fresh()->status)->toBe(AssignmentStatus::SUBMITTED_QC);

    $this->actingAs($this->employee)
        ->withSession(m3ReworkSession($this->employee))
        ->post(route('employee.work.qc-rework', [$this->assignment, $this->sourceReview]), [
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertSessionHasErrors('qc');

    expect(QcSubmission::query()->where('source_review_id', $this->sourceReview->id)->count())->toBe(1);

    $this->actingAs($this->qc)
        ->withSession(m3ReworkSession($this->qc))
        ->post(route('qc.submissions.start', $reworkSubmission))
        ->assertRedirect();

    $this->actingAs($this->qc)
        ->withSession(m3ReworkSession($this->qc))
        ->post(route('qc.submissions.finish', $reworkSubmission), [
            'approved_count' => 2,
            'rework_count' => 0,
        ])
        ->assertRedirect();

    expect($this->assignment->fresh()->status)->toBe(AssignmentStatus::COMPLETED)
        ->and($this->work->fresh()->status)->toBe(WorkOrderStatus::COMPLETED);
});
