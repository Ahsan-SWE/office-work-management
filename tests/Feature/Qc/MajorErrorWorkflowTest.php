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
use App\Enums\TeamStatus;
use App\Enums\UserStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkType;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\QcReason;
use App\Models\QcSubmission;
use App\Models\QcUserScope;
use App\Models\Team;
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

    $this->superAdmin = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->superAdmin->assignRole(RoleName::SUPER_ADMIN->value);

    $team = Team::query()->create([
        'name' => 'Major Error Team',
        'team_leader_id' => $this->leader->id,
        'status' => TeamStatus::ACTIVE,
        'created_by' => $this->superAdmin->id,
    ]);
    $this->employee->update(['primary_team_id' => $team->id]);

    QcUserScope::query()->create([
        'user_id' => $this->qc->id,
        'scope' => QcScope::ASSETS,
        'granted_at' => now(),
    ]);

    $client = Client::query()->create([
        'client_code' => 'CL-000001',
        'name' => 'Major Error Client',
        'normalized_name' => 'major error client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/major/edit',
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
        'assigned_count' => 10,
        'completed_count' => 10,
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
        'submitted_count' => 10,
        'status' => QcSubmissionStatus::WAITING,
        'submitted_at' => now(),
    ]);

    $this->reason = QcReason::query()
        ->where('type', QcReasonType::NEGATIVE->value)
        ->firstOrFail();
});

function m3b2MajorSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('requires a negative point when Major Error is selected', function () {
    $this->actingAs($this->qc)
        ->withSession(m3b2MajorSession($this->qc))
        ->post(route('qc.submissions.start', $this->submission))
        ->assertRedirect();

    $this->actingAs($this->qc)
        ->withSession(m3b2MajorSession($this->qc))
        ->post(route('qc.submissions.finish', $this->submission), [
            'approved_count' => 8,
            'rework_count' => 2,
            'is_major_error' => 1,
            'issues' => [
                ['reason_id' => $this->reason->id, 'negative_points' => 0],
            ],
        ])
        ->assertSessionHasErrors('qc');

    $review = $this->submission->review()->firstOrFail();

    expect($review->reviewed_at)->toBeNull()
        ->and($review->is_major_error)->toBeFalse()
        ->and($this->submission->fresh()->status)->toBe(QcSubmissionStatus::REVIEWING);
});

it('records a Major Error without changing the existing approve rework invariant', function () {
    $this->actingAs($this->qc)
        ->withSession(m3b2MajorSession($this->qc))
        ->post(route('qc.submissions.start', $this->submission))
        ->assertRedirect();

    $this->actingAs($this->qc)
        ->withSession(m3b2MajorSession($this->qc))
        ->post(route('qc.submissions.finish', $this->submission), [
            'approved_count' => 8,
            'rework_count' => 2,
            'is_major_error' => 1,
            'issues' => [
                ['reason_id' => $this->reason->id, 'negative_points' => 2, 'comment' => 'Major issue'],
            ],
        ])
        ->assertRedirect();

    $review = $this->submission->review()->firstOrFail();

    expect($review->is_major_error)->toBeTrue()
        ->and($review->negative_points)->toBe(2)
        ->and($review->approved_count + $review->rework_count)->toBe(10)
        ->and($review->major_error_email_sent)->toBeFalse()
        ->and($this->assignment->fresh()->status)->toBe(AssignmentStatus::REWORK);
});
