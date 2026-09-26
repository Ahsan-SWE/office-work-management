<?php
namespace App\Actions\Work;
use App\Enums\AssignmentStatus;
use App\Enums\AuditAction;
use App\Enums\WorkOrderStatus;
use App\Models\Assignment;
use App\Models\AssignmentProgressLog;
use App\Models\OfficeNotification;
use App\Support\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

class StartAssignmentAction {
    public function __construct(private readonly AuditLogger $audit){}
    public function handle(Assignment $assignment,int $employeeId): Assignment {
        if($assignment->employee_id!==$employeeId) throw new DomainException('This assignment belongs to another employee.');
        if($assignment->status!==AssignmentStatus::PENDING) throw new DomainException('Only a pending assignment can be started.');
        return DB::transaction(function() use($assignment,$employeeId){
            $assignment->loadMissing(['employee.primaryTeam.teamLeader','workOrder.client','workOrder.creator']);
            $assignment->update(['status'=>AssignmentStatus::ONGOING,'started_at'=>now()]);
            AssignmentProgressLog::query()->create([
                'assignment_id'=>$assignment->id,'user_id'=>$employeeId,'event_type'=>'STARTED',
                'from_count'=>$assignment->completed_count,'to_count'=>$assignment->completed_count,
                'note'=>'Employee started work.','created_at'=>now(),
            ]);
            $assignment->workOrder()->update(['status'=>WorkOrderStatus::IN_PROGRESS]);
            $workOrder=$assignment->workOrder;
            $recipientIds=collect([
                $workOrder->creator?->id,
                $assignment->employee->primaryTeam?->teamLeader?->id,
            ])->filter()->map(fn($id)=>(int)$id)->unique()->reject(fn($id)=>$id===$employeeId);

            foreach($recipientIds as $recipientId){
                OfficeNotification::query()->create([
                    'user_id'=>$recipientId,'type'=>'ASSIGNMENT_STARTED',
                    'title'=>"{$assignment->assignment_code} started",
                    'message'=>"{$assignment->employee->name} started {$workOrder->work_code} for {$workOrder->client->name}.",
                    'related_type'=>Assignment::class,'related_id'=>$assignment->id,'requires_action'=>false,
                ]);
            }
            $this->audit->log(AuditAction::ASSIGNMENT_STARTED->value,$assignment,null,
                ['status'=>AssignmentStatus::PENDING->value],
                ['status'=>AssignmentStatus::ONGOING->value,'started_at'=>$assignment->fresh()->started_at?->toIso8601String()]
            );
            return $assignment->fresh(['workOrder.client','employee','section']);
        });
    }
}
