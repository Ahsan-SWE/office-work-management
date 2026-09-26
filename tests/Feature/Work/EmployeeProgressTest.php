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

beforeEach(function(){
    $this->seed(RolePermissionSeeder::class);
    $this->seed(M2WorkFoundationSeeder::class);
    $this->leader=User::factory()->create(['status'=>UserStatus::ACTIVE,'session_version'=>1]); $this->leader->assignRole(RoleName::TEAM_LEADER->value);
    $this->employee=User::factory()->create(['status'=>UserStatus::ACTIVE,'session_version'=>1]); $this->employee->assignRole(RoleName::EMPLOYEE->value);
    $this->other=User::factory()->create(['status'=>UserStatus::ACTIVE,'session_version'=>1]); $this->other->assignRole(RoleName::EMPLOYEE->value);
    $this->client=Client::query()->create([
        'client_code'=>'CL-000001','name'=>'Progress Client','normalized_name'=>'progress client',
        'google_sheet_url'=>'https://docs.google.com/spreadsheets/d/progress/edit','status'=>ClientStatus::ACTIVE,
        'created_by'=>$this->leader->id,'updated_by'=>$this->leader->id,
    ]);
    $this->work=WorkOrder::query()->create([
        'work_code'=>'AW-000001','client_id'=>$this->client->id,'work_type'=>WorkType::ASSETS,
        'status'=>WorkOrderStatus::PENDING,'priority'=>Priority::HIGH,'sheet_url_snapshot'=>$this->client->google_sheet_url,'created_by'=>$this->leader->id,
    ]);
    $this->assignment=Assignment::query()->create([
        'assignment_code'=>'ASN-000001','work_order_id'=>$this->work->id,'employee_id'=>$this->employee->id,
        'scope_type'=>ScopeType::FULL_SECTION,'assigned_count'=>20,'completed_count'=>0,'priority'=>Priority::HIGH,
        'status'=>AssignmentStatus::PENDING,'assigned_by'=>$this->leader->id,'assigned_at'=>now(),
    ]);
});
function m2progresssession(User $user): array {
    return ['office_session_version'=>$user->session_version,'office_absolute_expires_at'=>now()->addHours(24)->timestamp];
}

it('lets employee start own pending assignment',function(){
    $this->actingAs($this->employee)->withSession(m2progresssession($this->employee))
        ->post(route('employee.work.start',$this->assignment))->assertRedirect();
    expect($this->assignment->fresh()->status)->toBe(AssignmentStatus::ONGOING)
        ->and($this->work->fresh()->status)->toBe(WorkOrderStatus::IN_PROGRESS);
    $this->assertDatabaseHas('assignment_progress_logs',['assignment_id'=>$this->assignment->id,'event_type'=>'STARTED']);
});

it('blocks another employee from opening the assignment',function(){
    $this->actingAs($this->other)->withSession(m2progresssession($this->other))
        ->get(route('employee.work.show',$this->assignment))->assertForbidden();
});

it('preserves every progress update',function(){
    $this->assignment->update(['status'=>AssignmentStatus::ONGOING,'started_at'=>now()]);
    $this->actingAs($this->employee)->withSession(m2progresssession($this->employee))
        ->post(route('employee.work.progress',$this->assignment),['completed_count'=>8,'note'=>'First batch complete.'])->assertRedirect();
    $this->actingAs($this->employee)->withSession(m2progresssession($this->employee))
        ->post(route('employee.work.progress',$this->assignment),['completed_count'=>15,'note'=>'Second batch complete.'])->assertRedirect();

    expect($this->assignment->fresh()->completed_count)->toBe(15)->and($this->assignment->progressLogs()->count())->toBe(2);
    $this->assertDatabaseHas('assignment_progress_logs',['assignment_id'=>$this->assignment->id,'from_count'=>8,'to_count'=>15]);
});
