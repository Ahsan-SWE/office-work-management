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
use App\Models\QcGmailConnection;
use App\Models\QcMajorErrorEmailAttempt;
use App\Models\QcReason;
use App\Models\QcSubmission;
use App\Models\QcUserScope;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\M2ConfigurationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        'name' => 'Gmail Team',
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
        'name' => 'Gmail Client',
        'normalized_name' => 'gmail client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/gmail/edit',
        'status' => ClientStatus::ACTIVE,
        'created_by' => $this->leader->id,
        'updated_by' => $this->leader->id,
    ]);

    $work = WorkOrder::query()->create([
        'work_code' => 'AW-000001',
        'client_id' => $client->id,
        'work_type' => WorkType::ASSETS,
        'status' => WorkOrderStatus::QC_IN_PROGRESS,
        'priority' => Priority::URGENT,
        'sheet_url_snapshot' => $client->google_sheet_url,
        'created_by' => $this->leader->id,
    ]);

    $assignment = Assignment::query()->create([
        'assignment_code' => 'ASN-000001',
        'work_order_id' => $work->id,
        'employee_id' => $this->employee->id,
        'scope_type' => ScopeType::FULL_SECTION,
        'assigned_count' => 10,
        'completed_count' => 10,
        'priority' => Priority::URGENT,
        'status' => AssignmentStatus::SUBMITTED_QC,
        'assigned_by' => $this->leader->id,
        'assigned_at' => now(),
        'started_at' => now(),
    ]);

    $this->submission = QcSubmission::query()->create([
        'submission_code' => 'QCS-000001',
        'idempotency_key' => (string) Str::uuid(),
        'assignment_id' => $assignment->id,
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
        ->withSession(m3b2GmailSession($this->qc))
        ->post(route('qc.submissions.start', $this->submission));

    $this->actingAs($this->qc)
        ->withSession(m3b2GmailSession($this->qc))
        ->post(route('qc.submissions.finish', $this->submission), [
            'approved_count' => 8,
            'rework_count' => 2,
            'is_major_error' => 1,
            'issues' => [
                ['reason_id' => $reason->id, 'negative_points' => 2, 'comment' => 'Serious issue'],
            ],
        ]);

    $this->review = $this->submission->review()->firstOrFail();

    QcGmailConnection::query()->create([
        'user_id' => $this->qc->id,
        'google_user_id' => 'google-qc-1',
        'email' => $this->qc->email,
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'scopes' => ['openid', 'email', 'https://www.googleapis.com/auth/gmail.send'],
        'expires_at' => now()->addHour(),
        'connected_at' => now(),
        'last_refreshed_at' => now(),
    ]);
});

function m3b2GmailSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('sends Major Error Gmail from the reviewing QC with default and optional CC audit history', function () {
    Http::fake([
        'https://gmail.googleapis.com/*' => Http::response(['id' => 'gmail-message-123'], 200),
    ]);

    $extra = 'extra@example.com';

    $this->actingAs($this->qc)
        ->withSession(m3b2GmailSession($this->qc))
        ->post(route('qc.reviews.major-error-email', $this->review), [
            'extra_cc' => $extra,
        ])
        ->assertRedirect();

    $attempt = QcMajorErrorEmailAttempt::query()->firstOrFail();
    $review = $this->review->fresh();

    expect($attempt->status)->toBe('SENT')
        ->and($attempt->gmail_message_id)->toBe('gmail-message-123')
        ->and($attempt->recipient_email)->toBe($this->employee->email)
        ->and($attempt->cc_emails)->toContain($this->leader->email)
        ->and($attempt->cc_emails)->toContain($this->superAdmin->email)
        ->and($attempt->cc_emails)->toContain($extra)
        ->and($review->major_error_email_sent)->toBeTrue()
        ->and($review->major_error_email_sent_at)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'
        && str_starts_with($request->header('Authorization')[0] ?? '', 'Bearer ')
        && filled($request['raw'])
    );
});

it('blocks a duplicate Major Error Gmail after successful delivery', function () {
    Http::fake([
        'https://gmail.googleapis.com/*' => Http::response(['id' => 'gmail-message-1'], 200),
    ]);

    $this->actingAs($this->qc)
        ->withSession(m3b2GmailSession($this->qc))
        ->post(route('qc.reviews.major-error-email', $this->review))
        ->assertRedirect();

    $this->actingAs($this->qc)
        ->withSession(m3b2GmailSession($this->qc))
        ->post(route('qc.reviews.major-error-email', $this->review))
        ->assertSessionHasErrors('gmail');

    expect(QcMajorErrorEmailAttempt::query()->count())->toBe(1);
});
