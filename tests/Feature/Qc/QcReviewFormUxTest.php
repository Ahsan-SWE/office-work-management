<?php

use App\Enums\AssignmentStatus;
use App\Enums\ClientStatus;
use App\Enums\Priority;
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
use App\Models\QcSubmission;
use App\Models\QcUserScope;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\M2ConfigurationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function qcReviewUxSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('starts with one issue and one bonus row and exposes dynamic add controls', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(M2ConfigurationSeeder::class);

    $leader = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $leader->assignRole(RoleName::TEAM_LEADER->value);

    $employee = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $employee->assignRole(RoleName::EMPLOYEE->value);

    $qc = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $qc->assignRole(RoleName::QC->value);

    QcUserScope::query()->create([
        'user_id' => $qc->id,
        'scope' => QcScope::ASSETS,
        'granted_at' => now(),
    ]);

    $client = Client::query()->create([
        'client_code' => 'CL-000001',
        'name' => 'QC UX Client',
        'normalized_name' => 'qc ux client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/qc-ux/edit',
        'status' => ClientStatus::ACTIVE,
        'created_by' => $leader->id,
        'updated_by' => $leader->id,
    ]);

    $work = WorkOrder::query()->create([
        'work_code' => 'AW-000001',
        'client_id' => $client->id,
        'work_type' => WorkType::ASSETS,
        'status' => WorkOrderStatus::QC_IN_PROGRESS,
        'priority' => Priority::HIGH,
        'sheet_url_snapshot' => $client->google_sheet_url,
        'created_by' => $leader->id,
    ]);

    $assignment = Assignment::query()->create([
        'assignment_code' => 'ASN-000001',
        'work_order_id' => $work->id,
        'employee_id' => $employee->id,
        'scope_type' => ScopeType::FULL_SECTION,
        'assigned_count' => 10,
        'completed_count' => 10,
        'priority' => Priority::HIGH,
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
        'submitted_count' => 10,
        'status' => QcSubmissionStatus::WAITING,
        'submitted_at' => now(),
    ]);

    $this->actingAs($qc)
        ->withSession(qcReviewUxSession($qc))
        ->post(route('qc.submissions.start', $submission))
        ->assertRedirect();

    $this->actingAs($qc)
        ->withSession(qcReviewUxSession($qc))
        ->get(route('qc.submissions.show', $submission))
        ->assertOk()
        ->assertSee('Issue 1')
        ->assertDontSee('Issue 2')
        ->assertSee('Bonus 1')
        ->assertDontSee('Bonus 2')
        ->assertSee('+ Add Issue')
        ->assertSee('+ Add Bonus')
        ->assertSee('data-qc-scroll-panel', false)
        ->assertSee('id="qc-issue-template"', false)
        ->assertSee('id="qc-bonus-template"', false);
});
