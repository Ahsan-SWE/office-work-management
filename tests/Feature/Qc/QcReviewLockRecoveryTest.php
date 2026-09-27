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

    $this->qcOne = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->qcOne->assignRole(RoleName::QC->value);

    $this->qcTwo = User::factory()->create(['status' => UserStatus::ACTIVE, 'session_version' => 1]);
    $this->qcTwo->assignRole(RoleName::QC->value);

    foreach ([$this->qcOne, $this->qcTwo] as $qc) {
        QcUserScope::query()->create([
            'user_id' => $qc->id,
            'scope' => QcScope::ASSETS,
            'granted_at' => now(),
        ]);
    }

    $client = Client::query()->create([
        'client_code' => 'CL-000001',
        'name' => 'Recovery Client',
        'normalized_name' => 'recovery client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/recovery/edit',
        'status' => ClientStatus::ACTIVE,
        'created_by' => $this->leader->id,
        'updated_by' => $this->leader->id,
    ]);

    $work = WorkOrder::query()->create([
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
        'work_order_id' => $work->id,
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
});

function m3b2LockSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('allows stale lock recovery while preserving lock history and one review row', function () {
    $this->actingAs($this->qcOne)
        ->withSession(m3b2LockSession($this->qcOne))
        ->post(route('qc.submissions.start', $this->submission))
        ->assertRedirect();

    $review = $this->submission->review()->firstOrFail();

    $this->actingAs($this->qcTwo)
        ->withSession(m3b2LockSession($this->qcTwo))
        ->post(route('qc.submissions.release', $this->submission), [
            'release_reason' => 'Should not be allowed before timeout.',
        ])
        ->assertSessionHasErrors('qc');

    $review->update(['started_at' => now()->subMinutes(121)]);

    $this->actingAs($this->qcTwo)
        ->withSession(m3b2LockSession($this->qcTwo))
        ->post(route('qc.submissions.release', $this->submission), [
            'release_reason' => 'Original reviewer abandoned the stale review.',
        ])
        ->assertRedirect(route('qc.queue', ['tab' => 'waiting']));

    expect($this->submission->fresh()->status)->toBe(QcSubmissionStatus::WAITING)
        ->and($review->fresh()->released_at)->not->toBeNull();

    $this->actingAs($this->qcTwo)
        ->withSession(m3b2LockSession($this->qcTwo))
        ->post(route('qc.submissions.start', $this->submission))
        ->assertRedirect();

    $reacquired = $this->submission->review()->firstOrFail();

    expect($this->submission->review()->count())->toBe(1)
        ->and($reacquired->id)->toBe($review->id)
        ->and($reacquired->reviewer_id)->toBe($this->qcTwo->id)
        ->and($reacquired->released_at)->toBeNull()
        ->and($reacquired->lockEvents()->count())->toBe(3);
});

it('blocks QC start for assignments already in duplicate review or completed states', function () {
    $this->assignment->update(['status' => AssignmentStatus::DUPLICATE_REVIEW]);

    $this->actingAs($this->qcOne)
        ->withSession(m3b2LockSession($this->qcOne))
        ->post(route('qc.submissions.start', $this->submission))
        ->assertSessionHasErrors('qc');

    expect($this->submission->fresh()->status)->toBe(QcSubmissionStatus::WAITING)
        ->and($this->submission->review()->count())->toBe(0);

    $this->assignment->update(['status' => AssignmentStatus::COMPLETED]);

    $this->actingAs($this->qcOne)
        ->withSession(m3b2LockSession($this->qcOne))
        ->post(route('qc.submissions.start', $this->submission))
        ->assertSessionHasErrors('qc');

    expect($this->submission->review()->count())->toBe(0);
});
