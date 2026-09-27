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
use App\Models\QcReviewEscalation;
use App\Models\QcReviewOverride;
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

    $this->superAdmin = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->superAdmin->assignRole(RoleName::SUPER_ADMIN->value);

    $this->leader = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->leader->assignRole(RoleName::TEAM_LEADER->value);

    $this->employee = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->employee->assignRole(RoleName::EMPLOYEE->value);

    $this->qc = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->qc->assignRole(RoleName::QC->value);

    $team = Team::query()->create([
        'name' => 'Appeal Team',
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
        'name' => 'Appeal Client',
        'normalized_name' => 'appeal client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/appeal/edit',
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

    $reason = QcReason::query()
        ->where('type', QcReasonType::NEGATIVE->value)
        ->firstOrFail();

    $this->actingAs($this->qc)
        ->withSession(m3b2AppealSession($this->qc))
        ->post(route('qc.submissions.start', $this->submission));

    $this->actingAs($this->qc)
        ->withSession(m3b2AppealSession($this->qc))
        ->post(route('qc.submissions.finish', $this->submission), [
            'approved_count' => 8,
            'rework_count' => 2,
            'issues' => [
                ['reason_id' => $reason->id, 'negative_points' => 1],
            ],
        ]);

    $this->review = $this->submission->review()->firstOrFail();
});

function m3b2AppealSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('lets Team Leader appeal and Super Admin override without mutating original QC review', function () {
    $this->actingAs($this->leader)
        ->withSession(m3b2AppealSession($this->leader))
        ->post(route('team-leader.qc-reviews.appeal', $this->review), [
            'reason' => 'The returned count should be reconsidered based on the task evidence.',
        ])
        ->assertRedirect();

    $escalation = QcReviewEscalation::query()->firstOrFail();

    expect($escalation->status)->toBe('OPEN')
        ->and($escalation->qc_review_id)->toBe($this->review->id);

    $this->actingAs($this->superAdmin)
        ->withSession(m3b2AppealSession($this->superAdmin))
        ->post(route('admin.qc-escalations.override', $escalation), [
            'approved_count' => 10,
            'rework_count' => 0,
            'negative_points' => 0,
            'bonus_points' => 0,
            'is_major_error' => 0,
            'reason' => 'Evidence supports full approval after Super Admin review.',
        ])
        ->assertRedirect();

    $original = $this->review->fresh();
    $override = QcReviewOverride::query()->firstOrFail();

    expect($original->approved_count)->toBe(8)
        ->and($original->rework_count)->toBe(2)
        ->and($override->approved_count)->toBe(10)
        ->and($override->rework_count)->toBe(0)
        ->and($original->effectiveApprovedCount())->toBe(10)
        ->and($original->effectiveReworkCount())->toBe(0)
        ->and($this->assignment->fresh()->status)->toBe(AssignmentStatus::COMPLETED)
        ->and($this->work->fresh()->status)->toBe(WorkOrderStatus::COMPLETED);

    $this->actingAs($this->superAdmin)
        ->withSession(m3b2AppealSession($this->superAdmin))
        ->post(route('admin.qc-escalations.resolve', $escalation), [
            'resolution_note' => 'Appeal reviewed and resolved with the recorded override.',
        ])
        ->assertRedirect();

    expect($escalation->fresh()->status)->toBe('RESOLVED');

    $this->actingAs($this->superAdmin)
        ->withSession(m3b2AppealSession($this->superAdmin))
        ->post(route('admin.qc-escalations.override', $escalation), [
            'approved_count' => 8,
            'rework_count' => 2,
            'negative_points' => 1,
            'bonus_points' => 0,
            'is_major_error' => 0,
            'reason' => 'A resolved escalation must reject any further override attempt.',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('override');

    expect(QcReviewOverride::query()->count())->toBe(1);

    $this->actingAs($this->superAdmin)
        ->withSession(m3b2AppealSession($this->superAdmin))
        ->get(route('admin.qc-escalations.show', $escalation))
        ->assertOk()
        ->assertSee('Resolved escalation is read-only.')
        ->assertDontSee('Record Override');
});
