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
use Database\Seeders\M2WorkFoundationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(M2WorkFoundationSeeder::class);

    $this->leader = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $this->leader->assignRole(RoleName::TEAM_LEADER->value);

    $this->employee = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $this->employee->assignRole(RoleName::EMPLOYEE->value);

    $this->client = Client::query()->create([
        'client_code' => 'CL-000001',
        'name' => 'Sheet Link Client',
        'normalized_name' => 'sheet link client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/current-sheet/edit',
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
        'sheet_url_snapshot' => 'https://docs.google.com/spreadsheets/d/original-sheet/edit',
        'created_by' => $this->leader->id,
    ]);

    $this->assignment = Assignment::query()->create([
        'assignment_code' => 'ASN-000001',
        'work_order_id' => $this->work->id,
        'employee_id' => $this->employee->id,
        'scope_type' => ScopeType::FULL_SECTION,
        'assigned_count' => 10,
        'completed_count' => 0,
        'priority' => Priority::HIGH,
        'status' => AssignmentStatus::ONGOING,
        'assigned_by' => $this->leader->id,
        'assigned_at' => now(),
        'started_at' => now(),
    ]);
});

function currentSheetSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('shows current client sheet and historical creation snapshot to the team leader', function () {
    $this->actingAs($this->leader)
        ->withSession(currentSheetSession($this->leader))
        ->get(route('work.show', $this->work))
        ->assertOk()
        ->assertSee('Current Client Sheet')
        ->assertSee('Created With Sheet')
        ->assertSee('https://docs.google.com/spreadsheets/d/current-sheet/edit', false)
        ->assertSee('https://docs.google.com/spreadsheets/d/original-sheet/edit', false)
        ->assertSee('The Client Sheet has changed since this Work Order was created.');
});

it('shows current client sheet and historical creation snapshot to the assigned employee', function () {
    $this->actingAs($this->employee)
        ->withSession(currentSheetSession($this->employee))
        ->get(route('employee.work.show', $this->assignment))
        ->assertOk()
        ->assertSee('Current Client Sheet')
        ->assertSee('Created With Sheet')
        ->assertSee('Open Current Google Sheet')
        ->assertSee('Open Historical Snapshot')
        ->assertSee('The Client Sheet has changed.');
});
