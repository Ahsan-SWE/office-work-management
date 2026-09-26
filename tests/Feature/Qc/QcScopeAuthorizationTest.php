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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

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

    $this->client = Client::query()->create([
        'client_code' => 'CL-000001',
        'name' => 'Scope Client',
        'normalized_name' => 'scope client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/scope/edit',
        'status' => ClientStatus::ACTIVE,
        'created_by' => $this->leader->id,
        'updated_by' => $this->leader->id,
    ]);
});

function m3ScopeSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('blocks a QC reviewer from a submission outside the active QC scope', function () {
    $work = WorkOrder::query()->create([
        'work_code' => 'SA-000001',
        'client_id' => $this->client->id,
        'work_type' => WorkType::SOCIAL,
        'status' => WorkOrderStatus::QC_IN_PROGRESS,
        'priority' => Priority::NORMAL,
        'sheet_url_snapshot' => $this->client->google_sheet_url,
        'created_by' => $this->leader->id,
    ]);

    $assignment = Assignment::query()->create([
        'assignment_code' => 'ASN-000001',
        'work_order_id' => $work->id,
        'employee_id' => $this->employee->id,
        'scope_type' => ScopeType::FULL_SECTION,
        'assigned_count' => 5,
        'completed_count' => 5,
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
        'submitted_count' => 5,
        'status' => QcSubmissionStatus::WAITING,
        'submitted_at' => now(),
    ]);

    $this->actingAs($this->qc)
        ->withSession(m3ScopeSession($this->qc))
        ->get(route('qc.submissions.show', $submission))
        ->assertForbidden();
});
