<?php
use App\Enums\Capability;
use App\Enums\ClientStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Enums\WorkType;
use App\Models\AssetSection;
use App\Models\Client;
use App\Models\SocialActivityType;
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
    $this->team=Team::query()->create(['name'=>'Work Team','team_leader_id'=>$this->leader->id,'status'=>'ACTIVE','created_by'=>$this->leader->id]);
    $this->leader->update(['primary_team_id'=>$this->team->id]);

    $this->employee=User::factory()->create(['status'=>UserStatus::ACTIVE,'session_version'=>1,'primary_team_id'=>$this->team->id]);
    $this->employee->assignRole(RoleName::EMPLOYEE->value);
    foreach([Capability::ASSETS,Capability::SOCIAL,Capability::CUSTOM] as $capability){
        UserCapability::query()->create(['user_id'=>$this->employee->id,'capability'=>$capability,'created_at'=>now()]);
    }

    $this->client=Client::query()->create([
        'client_code'=>'CL-000001','name'=>'Batch 2 Client','normalized_name'=>'batch 2 client',
        'google_sheet_url'=>'https://docs.google.com/spreadsheets/d/snapshot-one/edit','status'=>ClientStatus::ACTIVE,
        'created_by'=>$this->leader->id,'updated_by'=>$this->leader->id,
    ]);
});

function m2b2session(User $user): array {
    return ['office_session_version'=>$user->session_version,'office_absolute_expires_at'=>now()->addHours(24)->timestamp];
}

it('creates assets work with independent assignments and snapshots the sheet url',function(){
    $social=AssetSection::query()->where('name','Social Assets')->firstOrFail();
    $wp=AssetSection::query()->where('name','WordPress')->firstOrFail();

    $this->actingAs($this->leader)->withSession(m2b2session($this->leader))
        ->post(route('work.store'),[
            'work_type'=>WorkType::ASSETS->value,'client_id'=>$this->client->id,'priority'=>'HIGH',
            'assignments'=>[
                ['employee_id'=>$this->employee->id,'section_id'=>$social->id,'scope_type'=>'FULL_SECTION','assigned_count'=>20],
                ['employee_id'=>$this->employee->id,'section_id'=>$wp->id,'scope_type'=>'SPECIFIC_SITES','scope_text'=>'site-one.example, site-two.example','assigned_count'=>2],
            ],
        ])->assertRedirect();

    $work=WorkOrder::query()->firstOrFail();
    expect($work->work_code)->toBe('AW-000001')
        ->and($work->sheet_url_snapshot)->toBe('https://docs.google.com/spreadsheets/d/snapshot-one/edit')
        ->and($work->assignments()->count())->toBe(2)
        ->and($work->assignments()->orderBy('id')->pluck('assignment_code')->all())->toBe(['ASN-000001','ASN-000002']);

    $this->client->update(['google_sheet_url'=>'https://docs.google.com/spreadsheets/d/snapshot-two/edit']);
    expect($work->fresh()->sheet_url_snapshot)->toBe('https://docs.google.com/spreadsheets/d/snapshot-one/edit');
});

it('uses independent work code sequences and snapshots full social activity',function(){
    $section=AssetSection::query()->where('name','Social Assets')->firstOrFail();

    $this->actingAs($this->leader)->withSession(m2b2session($this->leader))
        ->post(route('work.store'),[
            'work_type'=>WorkType::ASSETS->value,'client_id'=>$this->client->id,'priority'=>'NORMAL',
            'assignments'=>[['employee_id'=>$this->employee->id,'section_id'=>$section->id,'scope_type'=>'FULL_SECTION','assigned_count'=>5]],
        ])->assertRedirect();

    $this->actingAs($this->leader)->withSession(m2b2session($this->leader))
        ->post(route('work.store'),[
            'work_type'=>WorkType::SOCIAL->value,'client_id'=>$this->client->id,'priority'=>'NORMAL','full_activity'=>1,
            'assignments'=>[['employee_id'=>$this->employee->id,'section_id'=>$section->id,'scope_type'=>'FULL_SECTION','assigned_count'=>5]],
        ])->assertRedirect();

    $assets=WorkOrder::query()->where('work_type','ASSETS')->firstOrFail();
    $social=WorkOrder::query()->where('work_type','SOCIAL')->firstOrFail();
    $fullCount=SocialActivityType::query()->where('is_full_activity_default',true)->count();

    expect($assets->work_code)->toBe('AW-000001')
        ->and($social->work_code)->toBe('SA-000001')
        ->and($social->configuration_snapshot['full_activity'])->toBeTrue()
        ->and(count($social->configuration_snapshot['activities']))->toBe($fullCount);
});

it('blocks new work for inactive clients',function(){
    $this->client->update(['status'=>ClientStatus::INACTIVE]);
    $section=AssetSection::query()->firstOrFail();

    $this->actingAs($this->leader)->withSession(m2b2session($this->leader))
        ->post(route('work.store'),[
            'work_type'=>'ASSETS','client_id'=>$this->client->id,'priority'=>'NORMAL',
            'assignments'=>[['employee_id'=>$this->employee->id,'section_id'=>$section->id,'scope_type'=>'FULL_SECTION']],
        ])->assertSessionHasErrors('client_id');

    expect(WorkOrder::query()->count())->toBe(0);
});
