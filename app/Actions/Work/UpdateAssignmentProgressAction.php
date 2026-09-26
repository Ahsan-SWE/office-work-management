<?php
namespace App\Actions\Work;
use App\Enums\AssignmentStatus;
use App\Enums\AuditAction;
use App\Models\Assignment;
use App\Models\AssignmentProgressLog;
use App\Support\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateAssignmentProgressAction {
    public function __construct(private readonly AuditLogger $audit){}
    public function handle(Assignment $assignment,int $employeeId,?int $completedCount,?string $note): Assignment {
        if($assignment->employee_id!==$employeeId) throw new DomainException('This assignment belongs to another employee.');
        if(!in_array($assignment->status,[AssignmentStatus::ONGOING,AssignmentStatus::REWORK],true))
            throw new DomainException('Progress can only be updated for ongoing or rework assignments.');
        $from=$assignment->completed_count;
        if($completedCount!==null){
            if($completedCount<$from) throw new DomainException('Completed count cannot be reduced.');
            if($assignment->assigned_count!==null && $completedCount>$assignment->assigned_count)
                throw new DomainException('Completed count cannot exceed assigned count.');
        } else $completedCount=$from;
        if($completedCount===$from && blank($note)) throw new DomainException('Add a progress count or a progress note.');
        return DB::transaction(function() use($assignment,$employeeId,$from,$completedCount,$note){
            $assignment->update(['completed_count'=>$completedCount]);
            AssignmentProgressLog::query()->create([
                'assignment_id'=>$assignment->id,'user_id'=>$employeeId,'event_type'=>'PROGRESS',
                'from_count'=>$from,'to_count'=>$completedCount,'note'=>$note,'created_at'=>now(),
            ]);
            $this->audit->log(AuditAction::ASSIGNMENT_PROGRESS_UPDATED->value,$assignment,null,
                ['completed_count'=>$from],['completed_count'=>$completedCount,'note'=>$note]);
            return $assignment->fresh(['progressLogs.user']);
        });
    }
}
