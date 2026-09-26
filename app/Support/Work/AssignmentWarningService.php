<?php
namespace App\Support\Work;
use App\Enums\AssignmentStatus;
use App\Enums\Capability;
use App\Enums\WorkType;
use App\Models\Assignment;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Collection;
class AssignmentWarningService {
    public function warnings(WorkType $workType,array $employeeIds): Collection {
        $ids=collect($employeeIds)->filter()->map(fn($id)=>(int)$id)->unique()->values();
        if($ids->isEmpty()) return collect();
        $employees=User::query()->with('capabilities')->whereIn('id',$ids)->get()->keyBy('id');
        $workloads=Assignment::query()
            ->selectRaw('employee_id, COUNT(*) AS workload_count')
            ->whereIn('employee_id',$ids)
            ->whereIn('status',[AssignmentStatus::PENDING->value,AssignmentStatus::ONGOING->value,AssignmentStatus::REWORK->value])
            ->groupBy('employee_id')->pluck('workload_count','employee_id');
        $threshold=SystemSetting::integer('workload_warning_threshold',8);
        $required=Capability::from($workType->value);
        return $ids->flatMap(function(int $id) use($employees,$workloads,$threshold,$required,$workType){
            $employee=$employees->get($id); if(!$employee) return [];
            $warnings=[];
            $caps=$employee->capabilities->pluck('capability')->map(fn($c)=>$c->value)->all();
            if(!in_array($required->value,$caps,true)){
                $warnings[]=['employee_id'=>$id,'type'=>'CAPABILITY_MISMATCH','message'=>"{$employee->name} does not currently have {$workType->value} capability."];
            }
            $count=(int)($workloads[$id]??0);
            if($count >= $threshold){
                $warnings[]=['employee_id'=>$id,'type'=>'WORKLOAD','message'=>"{$employee->name} currently has {$count} active task(s). Warning threshold is {$threshold}."];
            }
            return $warnings;
        })->values();
    }
}
