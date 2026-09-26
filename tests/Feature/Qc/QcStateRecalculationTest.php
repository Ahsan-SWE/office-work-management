<?php

use App\Enums\AssignmentStatus;
use App\Enums\ClientStatus;
use App\Enums\Priority;
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
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Qc\AssignmentQcStateService;
use App\Services\Qc\WorkOrderQcStateService;
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

    $client = Client::query()->create([
        'client_code' => 'CL-000001',
        'name' => 'State Client',
        'normalized_name' => 'state client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/state/edit',
        'status' => ClientStatus::ACTIVE,
        'created_by' => $this->leader->id,
        'updated_by' => $this->leader->id,
    ]);

    $this->work = WorkOrder::query()->create([
        'work_code' => 'AW-000001',
        'client_id' => $client->id,
        'work_type' => WorkType::ASSETS,
        'status' => WorkOrderStatus::IN_PROGRESS,
        'priority' => Priority::NORMAL,
        'sheet_url_snapshot' => $client->google_sheet_url,
        'created_by' => $this->leader->id,
    ]);

    $this->assignment = Assignment::query()->create([
        'assignment_code' => 'ASN-000001',
        'work_order_id' => $this->work->id,
        'employee_id' => $this->employee->id,
        'scope_type' => ScopeType::FULL_SECTION,
        'assigned_count' => 40,
        'completed_count' => 20,
        'priority' => Priority::NORMAL,
        'status' => AssignmentStatus::ONGOING,
        'assigned_by' => $this->leader->id,
        'assigned_at' => now(),
        'started_at' => now(),
    ]);
});

it('keeps partial work ongoing while parent reflects active QC', function () {
    QcSubmission::query()->create([
        'submission_code' => 'QCS-000001',
        'idempotency_key' => (string) Str::uuid(),
        'assignment_id' => $this->assignment->id,
        'submitted_by' => $this->employee->id,
        'submission_type' => QcSubmissionType::PARTIAL,
        'submitted_count' => 20,
        'status' => QcSubmissionStatus::WAITING,
        'submitted_at' => now(),
    ]);

    app(AssignmentQcStateService::class)->recalculate($this->assignment);
    app(WorkOrderQcStateService::class)->recalculate($this->work);

    expect($this->assignment->fresh()->status)->toBe(AssignmentStatus::ONGOING)
        ->and($this->work->fresh()->status)->toBe(WorkOrderStatus::QC_IN_PROGRESS);
});

it('moves fully submitted original scope to submitted QC', function () {
    $this->assignment->update(['completed_count' => 40]);

    QcSubmission::query()->create([
        'submission_code' => 'QCS-000001',
        'idempotency_key' => (string) Str::uuid(),
        'assignment_id' => $this->assignment->id,
        'submitted_by' => $this->employee->id,
        'submission_type' => QcSubmissionType::FINAL,
        'submitted_count' => 40,
        'status' => QcSubmissionStatus::WAITING,
        'submitted_at' => now(),
    ]);

    app(AssignmentQcStateService::class)->recalculate($this->assignment);

    expect($this->assignment->fresh()->status)->toBe(AssignmentStatus::SUBMITTED_QC);
});
