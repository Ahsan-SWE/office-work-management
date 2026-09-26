<?php
namespace App\Http\Controllers;

use App\Actions\Work\CreateWorkOrderAction;
use App\Enums\AssignmentStatus;
use App\Enums\ClientStatus;
use App\Enums\Priority;
use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Enums\WorkType;
use App\Models\AssetSection;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\CustomJobType;
use App\Models\SocialActivityType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Work\AssignmentWarningService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('work.assign'),403);
        $query=WorkOrder::query()->with(['client:id,client_code,name','creator:id,name,email'])->withCount('assignments');

        if(!$request->user()->hasRole('SUPER_ADMIN')){
            $teamId=$request->user()->primary_team_id;
            $query->where(function(Builder $q) use($request,$teamId){
                $q->where('created_by',$request->user()->id)
                  ->orWhereHas('assignments.employee',fn(Builder $e)=>$e->where('primary_team_id',$teamId));
            });
        }

        if($request->filled('q')){
            $needle='%'.mb_strtolower(trim($request->string('q')->toString())).'%';
            $query->where(function(Builder $q) use($needle){
                $q->whereRaw('LOWER(work_code) LIKE ?',[$needle])
                  ->orWhereHas('client',fn(Builder $c)=>$c->whereRaw('LOWER(name) LIKE ?',[$needle]));
            });
        }
        if($request->filled('work_type')) $query->where('work_type',$request->string('work_type')->toString());
        if($request->filled('status')) $query->where('status',$request->string('status')->toString());

        return view('work.index',[
            'workOrders'=>$query->latest()->paginate(30)->withQueryString(),
            'workTypes'=>WorkType::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('work.assign'),403);
        $workType=WorkType::tryFrom(strtoupper($request->string('type',WorkType::ASSETS->value)->toString()))??WorkType::ASSETS;
        return view('work.create',[
            'workType'=>$workType,
            'workTypes'=>WorkType::cases(),
            'priorities'=>Priority::cases(),
            'clients'=>Client::query()->where('status',ClientStatus::ACTIVE->value)->orderBy('name')->get(['id','client_code','name','google_sheet_url']),
            'employees'=>$this->availableEmployees($request),
            'sections'=>AssetSection::query()->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(),
            'activities'=>SocialActivityType::query()->where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(),
            'customJobTypes'=>CustomJobType::query()->where('is_active',true)->orderBy('name')->get(),
            'preselectedClientId'=>$request->integer('client') ?: null,
        ]);
    }

    public function store(Request $request,AssignmentWarningService $warnings,CreateWorkOrderAction $action): RedirectResponse
    {
        abort_unless($request->user()->can('work.assign'),403);
        $workType=WorkType::tryFrom(strtoupper((string)$request->input('work_type')));
        if(!$workType) throw ValidationException::withMessages(['work_type'=>'Select a valid work type.']);

        $data=$this->validated($request,$workType);
        $client=Client::query()->findOrFail($data['client_id']);
        if($client->status!==ClientStatus::ACTIVE)
            throw ValidationException::withMessages(['client_id'=>'Inactive clients cannot receive new work.']);

        $allowed=$this->availableEmployees($request)->pluck('id')->map(fn($id)=>(int)$id);
        $submitted=collect($data['assignments'])->pluck('employee_id')->map(fn($id)=>(int)$id)->unique();
        if($submitted->diff($allowed)->isNotEmpty())
            throw ValidationException::withMessages(['assignments'=>'One or more selected employees are not available for this Team Leader.']);

        $assignmentWarnings=$warnings->warnings($workType,$submitted->all());
        if($assignmentWarnings->isNotEmpty() && !$request->boolean('confirm_warnings'))
            return back()->withInput()->with('assignment_warnings',$assignmentWarnings->all());

        $workOrder=$action->handle($client,$workType,$data,$request->user()->id);
        return redirect()->route('work.show',$workOrder)
            ->with('success',"{$workOrder->work_code} created with {$workOrder->assignments->count()} assignment(s).");
    }

    public function show(Request $request,WorkOrder $workOrder): View
    {
        abort_unless($request->user()->can('work.assign'),403);
        $this->authorizeVisibility($request,$workOrder);
        $workOrder->load([
            'client.currentTier','creator:id,name,email','customJobType:id,name',
            'assignments.employee:id,name,email,primary_team_id,status','assignments.section:id,name',
            'assignments.progressLogs.user:id,name,email',
        ]);
        return view('work.show',compact('workOrder'));
    }

    private function validated(Request $request,WorkType $workType): array
    {
        $rules=[
            'work_type'=>['required',Rule::enum(WorkType::class)],
            'client_id'=>['required','integer','exists:clients,id'],
            'priority'=>['required',Rule::enum(Priority::class)],
            'proof_required'=>['nullable','boolean'],
            'instruction'=>['nullable','string','max:20000'],
            'confirm_warnings'=>['nullable','boolean'],
            'assignments'=>['required','array','min:1'],
            'assignments.*.employee_id'=>['required','integer','exists:users,id'],
            'assignments.*.assigned_count'=>['nullable','integer','min:1','max:1000000'],
            'assignments.*.scope_text'=>['nullable','string','max:10000'],
        ];

        if(in_array($workType,[WorkType::ASSETS,WorkType::SOCIAL],true)){
            $rules['assignments.*.section_id']=['required',Rule::exists('asset_sections','id')->where('is_active',true)];
            $rules['assignments.*.scope_type']=['required',Rule::in([ScopeType::FULL_SECTION->value,ScopeType::SPECIFIC_SITES->value])];
        }

        if($workType===WorkType::SOCIAL){
            $rules['full_activity']=['nullable','boolean'];
            $rules['activity_type_ids']=['nullable','array'];
            $rules['activity_type_ids.*']=['integer',Rule::exists('social_activity_types','id')->where('is_active',true)];
        }

        if($workType===WorkType::CUSTOM){
            $rules['custom_job_type_id']=['nullable',Rule::exists('custom_job_types','id')->where('is_active',true)];
            $rules['custom_title']=['nullable','string','max:220'];
            $rules['instruction']=['required','string','max:20000'];
            $rules['special_custom_split']=['nullable','boolean'];
        }

        $data=Validator::make($request->all(),$rules)->validate();

        if($workType===WorkType::SOCIAL && !($data['full_activity']??false) && count($data['activity_type_ids']??[])===0)
            throw ValidationException::withMessages(['activity_type_ids'=>'Select at least one Social Activity or use Full Activity.']);

        if($workType===WorkType::CUSTOM){
            if(empty($data['custom_job_type_id']) && blank($data['custom_title']??null))
                throw ValidationException::withMessages(['custom_title'=>'Enter a custom job title when no predefined type is selected.']);
            if(!($data['special_custom_split']??false) && count($data['assignments'])!==1)
                throw ValidationException::withMessages(['assignments'=>'A normal Custom Job must have exactly one employee. Enable Special Custom Job to split it.']);
            foreach($data['assignments'] as &$a){$a['scope_type']=ScopeType::CUSTOM_SCOPE->value;$a['section_id']=$a['section_id']??null;}
            unset($a);
        }

        foreach($data['assignments'] as $i=>$a){
            if(in_array($workType,[WorkType::ASSETS,WorkType::SOCIAL],true)
                && ($a['scope_type']??null)===ScopeType::SPECIFIC_SITES->value
                && blank($a['scope_text']??null)){
                throw ValidationException::withMessages(["assignments.$i.scope_text"=>'Specific Sites / Partial Section requires a site list or scope.']);
            }
        }
        return $data;
    }

    private function availableEmployees(Request $request)
    {
        $query=User::query()->with('capabilities')->where('status',UserStatus::ACTIVE->value)->role('EMPLOYEE')->orderBy('name');
        if(!$request->user()->hasRole('SUPER_ADMIN')) $query->where('primary_team_id',$request->user()->primary_team_id);
        $employees=$query->get();
        $counts=Assignment::query()->selectRaw('employee_id, COUNT(*) AS workload_count')
            ->whereIn('employee_id',$employees->pluck('id'))
            ->whereIn('status',[AssignmentStatus::PENDING->value,AssignmentStatus::ONGOING->value,AssignmentStatus::REWORK->value])
            ->groupBy('employee_id')->pluck('workload_count','employee_id');
        return $employees->each(fn(User $u)=>$u->setAttribute('active_workload_count',(int)($counts[$u->id]??0)));
    }

    private function authorizeVisibility(Request $request,WorkOrder $workOrder): void
    {
        if($request->user()->hasRole('SUPER_ADMIN')) return;
        $teamId=$request->user()->primary_team_id;
        $visible=$workOrder->created_by===$request->user()->id
            || $workOrder->assignments()->whereHas('employee',fn(Builder $q)=>$q->where('primary_team_id',$teamId))->exists();
        abort_unless($visible,403);
    }
}
