<?php

namespace App\Services\Qc;

use App\Enums\AssignmentStatus;
use App\Enums\QcSubmissionStatus;
use App\Enums\WorkOrderStatus;
use App\Models\QcSubmission;
use App\Models\WorkOrder;

class WorkOrderQcStateService
{
    public function recalculate(WorkOrder $workOrder): WorkOrder
    {
        $workOrder = WorkOrder::query()->findOrFail($workOrder->id);

        if (in_array($workOrder->status, [
            WorkOrderStatus::CANCELLED,
            WorkOrderStatus::DUPLICATE,
        ], true)) {
            return $workOrder;
        }

        $assignments = $workOrder->assignments()->get(['id', 'work_order_id', 'status']);

        $allCompleted = $assignments->isNotEmpty()
            && $assignments->every(fn ($assignment) => $assignment->status === AssignmentStatus::COMPLETED);

        $hasRework = $assignments->contains(
            fn ($assignment) => $assignment->status === AssignmentStatus::REWORK
        );

        $hasActiveQc = QcSubmission::query()
            ->whereHas('assignment', fn ($query) => $query->where('work_order_id', $workOrder->id))
            ->whereIn('status', [
                QcSubmissionStatus::WAITING->value,
                QcSubmissionStatus::REVIEWING->value,
            ])
            ->exists();

        $hasStartedWork = $assignments->contains(
            fn ($assignment) => $assignment->status !== AssignmentStatus::PENDING
        );

        $nextStatus = match (true) {
            $allCompleted => WorkOrderStatus::COMPLETED,
            $hasRework => WorkOrderStatus::REWORK,
            $hasActiveQc => WorkOrderStatus::QC_IN_PROGRESS,
            $hasStartedWork => WorkOrderStatus::IN_PROGRESS,
            default => WorkOrderStatus::PENDING,
        };

        $workOrder->update(['status' => $nextStatus]);

        return $workOrder->fresh();
    }
}
