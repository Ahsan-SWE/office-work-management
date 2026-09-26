<?php
namespace App\Actions\Work;
use App\Enums\AssignmentStatus;
use App\Enums\AuditAction;
use App\Enums\Priority;
use App\Enums\ScopeType;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkType;
use App\Models\AssetSection;
use App\Models\Assignment;
use App\Models\Client;
use App\Models\CodeCounter;
use App\Models\CustomJobType;
use App\Models\SocialActivityType;
use App\Models\WorkOrder;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class CreateWorkOrderAction {
    public function __construct(private readonly AuditLogger $audit){}
    public function handle(Client $client,WorkType $workType,array $data,int $actorId): WorkOrder {
        return DB::transaction(function() use($client,$workType,$data,$actorId){
            $priority=Priority::from($data['priority']);
            $config=$this->configurationSnapshot($workType,$data);
            $workOrder=WorkOrder::query()->create([
                'work_code'=>$this->nextWorkCode($workType),'client_id'=>$client->id,'work_type'=>$workType,'status'=>WorkOrderStatus::PENDING,
                'priority'=>$priority,'sheet_url_snapshot'=>$client->google_sheet_url,'configuration_snapshot'=>$config,
                'custom_job_type_id'=>$data['custom_job_type_id']??null,'title'=>$this->title($workType,$data),
                'instruction'=>$data['instruction']??null,'proof_required'=>(bool)($data['proof_required']??false),
                'special_custom_split'=>(bool)($data['special_custom_split']??false),'created_by'=>$actorId,
            ]);
            foreach($data['assignments'] as $a){
                $section=!empty($a['section_id'])?AssetSection::query()->find($a['section_id']):null;
                $scopeType=$workType===WorkType::CUSTOM?ScopeType::CUSTOM_SCOPE:ScopeType::from($a['scope_type']);
                $scopeSnapshot=[
                    'section_id'=>$section?->id,'section_name'=>$section?->name,'scope_type'=>$scopeType->value,
                    'scope_text'=>$a['scope_text']??null,'assigned_count'=>isset($a['assigned_count'])?(int)$a['assigned_count']:null,
                ];
                if($workType===WorkType::SOCIAL){$scopeSnapshot['activities']=$config['activities']??[];$scopeSnapshot['full_activity']=$config['full_activity']??false;}
                if($workType===WorkType::CUSTOM){$scopeSnapshot['custom_job']=$config['custom_job']??null;}
                $assignment=Assignment::query()->create([
                    'assignment_code'=>null,'work_order_id'=>$workOrder->id,'employee_id'=>$a['employee_id'],'section_id'=>$section?->id,
                    'scope_type'=>$scopeType,'scope_text'=>$a['scope_text']??null,'scope_snapshot'=>$scopeSnapshot,
                    'assigned_count'=>$a['assigned_count']??null,'completed_count'=>0,'priority'=>$priority,'status'=>AssignmentStatus::PENDING,
                    'proof_required'=>(bool)($data['proof_required']??false),'instruction'=>$data['instruction']??null,
                    'assigned_by'=>$actorId,'assigned_at'=>now(),
                ]);
                $assignment->update(['assignment_code'=>sprintf('ASN-%06d',$assignment->id)]);
            }
            $this->audit->log(AuditAction::WORK_ORDER_CREATED->value,$workOrder,null,null,[
                'work_code'=>$workOrder->work_code,'client_id'=>$client->id,'work_type'=>$workType->value,
                'assignment_count'=>count($data['assignments']),'sheet_url_snapshot'=>$workOrder->sheet_url_snapshot,
            ]);
            return $workOrder->fresh(['client','assignments.employee','assignments.section']);
        });
    }
    private function nextWorkCode(WorkType $type): string {
        $prefix=$type->codePrefix();
        $counter=CodeCounter::query()->whereKey($prefix)->lockForUpdate()->firstOrFail();
        $number=$counter->next_value;
        $counter->update(['next_value'=>$number+1]);
        return sprintf('%s-%06d',$prefix,$number);
    }

    private function configurationSnapshot(WorkType $type,array $data): array {
        return match($type){
            WorkType::ASSETS=>['asset_sections'=>collect($data['assignments'])->pluck('section_id')->filter()->unique()
                ->map(fn($id)=>AssetSection::query()->find($id))->filter()
                ->map(fn(AssetSection $s)=>['id'=>$s->id,'name'=>$s->name])->values()->all()],
            WorkType::SOCIAL=>$this->socialSnapshot($data),
            WorkType::CUSTOM=>$this->customSnapshot($data),
        };
    }
    private function socialSnapshot(array $data): array {
        $full=(bool)($data['full_activity']??false);
        $q=SocialActivityType::query()->where('is_active',true);
        $full?$q->where('is_full_activity_default',true):$q->whereIn('id',$data['activity_type_ids']??[]);
        return ['full_activity'=>$full,'activities'=>$q->orderBy('sort_order')->get(['id','name'])->map(fn($a)=>['id'=>$a->id,'name'=>$a->name])->all()];
    }
    private function customSnapshot(array $data): array {
        $type=!empty($data['custom_job_type_id'])?CustomJobType::query()->find($data['custom_job_type_id']):null;
        return ['custom_job'=>['type_id'=>$type?->id,'type_name'=>$type?->name,'custom_title'=>$data['custom_title']??null,
            'instruction'=>$data['instruction']??null,'special_split'=>(bool)($data['special_custom_split']??false)]];
    }
    private function title(WorkType $type,array $data): ?string {
        if($type!==WorkType::CUSTOM) return null;
        return !empty($data['custom_job_type_id'])
            ? CustomJobType::query()->whereKey($data['custom_job_type_id'])->value('name')
            : ($data['custom_title']??'Custom Job');
    }
}
