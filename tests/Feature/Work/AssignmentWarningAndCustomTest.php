<?php
use App\Enums\Capability;
use App\Enums\ClientStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\AssetSection;
use App\Models\Client;
use App\Models\CustomJobType;
use App\Models\Team;
use App\Models\User;
use App\Models\UserCapability;
use App\Models\WorkOrder;
use Database\Seeders\M2ConfigurationSeeder;
use Database\Seeders\M2WorkFoundationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function(){
    $this->seed(RolePermissionSeeder::class);
    $this->seed(M2ConfigurationSeeder::class);
    $this->seed(M2WorkFoundationSeeder::class);
    $this->leader=User::factory()->create(['status'=>UserStatus::ACTIVE,'session_version'=>1]);
    $this->leader->assignRole(RoleName::TEAM_LEADER->value);
    $this->team=Team::query()->create(['name'=>'Warning Team','team_leader_id'=>$this->leader->id,'status'=>'ACTIVE','created_by'=>$this->leader->id]);
    $this->leader->update(['primary_team_id'=>$this->team->id]);
    $this->employee=User::factory()->create(['status'=>UserStatus::ACTIVE,'session_version'=>1,'primary_team_id'=>$this->team->id]);
    $this->employee->assignRole(RoleName::EMPLOYEE->value);
    $this->client=Client::query()->create([
        'client_code'=>'CL-000001','name'=>'Warning Client','normalized_name'=>'warning client',
        'google_sheet_url'=>'https://docs.google.com/spreadsheets/d/warning/edit','status'=>ClientStatus::ACTIVE,
        'created_by'=>$this->leader->id,'updated_by'=>$this->leader->id,
    ]);
});
function m2warnsession(User $user): array {
    return ['office_session_version'=>$user->session_version,'office_absolute_expires_at'=>now()->addHours(24)->timestamp];
}

it('warns on capability mismatch and allows explicit assign anyway',function(){
    $section=AssetSection::query()->firstOrFail();
    $payload=['work_type'=>'ASSETS','client_id'=>$this->client->id,'priority'=>'NORMAL',
        'assignments'=>[['employee_id'=>$this->employee->id,'section_id'=>$section->id,'scope_type'=>'FULL_SECTION','assigned_count'=>5]]];

    $this->actingAs($this->leader)->withSession(m2warnsession($this->leader))->from(route('work.create'))
        ->post(route('work.store'),$payload)->assertRedirect(route('work.create'))->assertSessionHas('assignment_warnings');
    expect(WorkOrder::query()->count())->toBe(0);

    $this->actingAs($this->leader)->withSession(m2warnsession($this->leader))
        ->post(route('work.store'),$payload+['confirm_warnings'=>1])->assertRedirect();
    expect(WorkOrder::query()->count())->toBe(1);
});

it('blocks team leader from assigning another teams employee',function(){
    $otherTeam=Team::query()->create(['name'=>'Other Team','status'=>'ACTIVE','created_by'=>$this->leader->id]);
    $other=User::factory()->create(['status'=>UserStatus::ACTIVE,'session_version'=>1,'primary_team_id'=>$otherTeam->id]);
    $other->assignRole(RoleName::EMPLOYEE->value);
    $section=AssetSection::query()->firstOrFail();

    $this->actingAs($this->leader)->withSession(m2warnsession($this->leader))
        ->post(route('work.store'),[
            'work_type'=>'ASSETS','client_id'=>$this->client->id,'priority'=>'NORMAL','confirm_warnings'=>1,
            'assignments'=>[['employee_id'=>$other->id,'section_id'=>$section->id,'scope_type'=>'FULL_SECTION']],
        ])->assertSessionHasErrors('assignments');
    expect(WorkOrder::query()->count())->toBe(0);
});

it('requires one employee normally but allows special custom split',function(){
    $second=User::factory()->create(['status'=>UserStatus::ACTIVE,'session_version'=>1,'primary_team_id'=>$this->team->id]);
    $second->assignRole(RoleName::EMPLOYEE->value);
    foreach([$this->employee,$second] as $employee){
        UserCapability::query()->create(['user_id'=>$employee->id,'capability'=>Capability::CUSTOM,'created_at'=>now()]);
    }
    $type=CustomJobType::query()->where('name','Image Optimization')->firstOrFail();
    $base=['work_type'=>'CUSTOM','client_id'=>$this->client->id,'priority'=>'URGENT','custom_job_type_id'=>$type->id,
        'instruction'=>'Split this optimization work.','assignments'=>[
            ['employee_id'=>$this->employee->id,'scope_text'=>'Images 1-20'],
            ['employee_id'=>$second->id,'scope_text'=>'Images 21-40'],
        ]];

    $this->actingAs($this->leader)->withSession(m2warnsession($this->leader))
        ->post(route('work.store'),$base)->assertSessionHasErrors('assignments');
    expect(WorkOrder::query()->count())->toBe(0);

    $this->actingAs($this->leader)->withSession(m2warnsession($this->leader))
        ->post(route('work.store'),$base+['special_custom_split'=>1])->assertRedirect();

    $work=WorkOrder::query()->firstOrFail();
    expect($work->work_code)->toBe('CJ-000001')->and($work->special_custom_split)->toBeTrue()->and($work->assignments()->count())->toBe(2);
});
