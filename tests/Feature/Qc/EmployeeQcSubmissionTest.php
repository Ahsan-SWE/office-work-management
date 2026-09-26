<?php

use App\Enums\AssignmentStatus;
use App\Enums\ClientStatus;
use App\Enums\Priority;
use App\Enums\RoleName;
use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkType;
use App\Models\Assignment;
use App\Models\Client;
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

    $this->client = Client::query()->create([
        'client_code' => 'CL-000001',
        'name' => 'QC Submit Client',
        'normalized_name' => 'qc submit client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/qc-submit/edit',
        'status' => ClientStatus::ACTIVE,
        'created_by' => $this->leader->id,
        'updated_by' => $this->leader->id,
    ]);

    $this->work = WorkOrder::query()->create([
        'work_code' => 'AW-000001',
        'client_id' => $this->client->id,
        'work_type' => WorkType::ASSETS,
        'status' => WorkOrderStatus::IN_PROGRESS,
        'priority' => Priority::HIGH,
        'sheet_url_snapshot' => $this->client->google_sheet_url,
        'created_by' => $this->leader->id,
    ]);

    $this->assignment = Assignment::query()->create([
        'assignment_code' => 'ASN-000001',
        'work_order_id' => $this->work->id,
        'employee_id' => $this->employee->id,
        'scope_type' => ScopeType::FULL_SECTION,
        'assigned_count' => 10,
        'completed_count' => 6,
        'priority' => Priority::HIGH,
        'status' => AssignmentStatus::ONGOING,
        'assigned_by' => $this->leader->id,
        'assigned_at' => now(),
        'started_at' => now(),
    ]);
});

function m3EmployeeSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('auto-selects partial then final submission type', function () {
    $this->actingAs($this->employee)
        ->withSession(m3EmployeeSession($this->employee))
        ->post(route('employee.work.qc-submit', $this->assignment), [
            'idempotency_key' => (string) Str::uuid(),
            'submitted_count' => 4,
            'scope_text' => 'First completed batch',
        ])
        ->assertRedirect();

    $first = $this->assignment->qcSubmissions()->firstOrFail();

    expect($first->submission_type->value)->toBe('PARTIAL')
        ->and($this->assignment->fresh()->status)->toBe(AssignmentStatus::ONGOING);

    $this->assignment->update(['completed_count' => 10]);

    $this->actingAs($this->employee)
        ->withSession(m3EmployeeSession($this->employee))
        ->post(route('employee.work.qc-submit', $this->assignment), [
            'idempotency_key' => (string) Str::uuid(),
            'submitted_count' => 6,
        ])
        ->assertRedirect();

    $second = $this->assignment->qcSubmissions()->latest('id')->firstOrFail();

    expect($second->submission_type->value)->toBe('FINAL')
        ->and($this->assignment->fresh()->status)->toBe(AssignmentStatus::SUBMITTED_QC);
});

it('uses the idempotency key to prevent duplicate employee submissions', function () {
    $key = (string) Str::uuid();

    $payload = [
        'idempotency_key' => $key,
        'submitted_count' => 4,
    ];

    $this->actingAs($this->employee)
        ->withSession(m3EmployeeSession($this->employee))
        ->post(route('employee.work.qc-submit', $this->assignment), $payload)
        ->assertRedirect();

    $this->actingAs($this->employee)
        ->withSession(m3EmployeeSession($this->employee))
        ->post(route('employee.work.qc-submit', $this->assignment), $payload)
        ->assertRedirect();

    expect($this->assignment->qcSubmissions()->count())->toBe(1);
});
