<?php

namespace App\Services\Qc;

use App\Enums\AssignmentStatus;
use App\Enums\QcSubmissionStatus;
use App\Enums\QcSubmissionType;
use App\Models\Assignment;
use App\Models\QcReview;
use App\Models\QcSubmission;
use Illuminate\Support\Collection;

class AssignmentQcStateService
{
    public function summary(Assignment $assignment): array
    {
        $effectiveTotal = $assignment->assigned_count ?? 1;
        $effectiveCompleted = $assignment->assigned_count === null
            ? ($assignment->started_at !== null || $assignment->status !== AssignmentStatus::PENDING ? 1 : 0)
            : min((int) $assignment->completed_count, (int) $effectiveTotal);

        $originalSubmitted = (int) QcSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->whereIn('submission_type', [
                QcSubmissionType::PARTIAL->value,
                QcSubmissionType::FINAL->value,
            ])
            ->sum('submitted_count');

        $approvedTotal = (int) QcReview::query()
            ->whereNotNull('reviewed_at')
            ->whereHas('submission', fn ($query) => $query->where('assignment_id', $assignment->id))
            ->sum('approved_count');

        $unresolvedReviews = $this->unresolvedReviews($assignment);

        $waitingCount = QcSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('status', QcSubmissionStatus::WAITING->value)
            ->count();

        $reviewingCount = QcSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('status', QcSubmissionStatus::REVIEWING->value)
            ->count();

        return [
            'effective_total' => $effectiveTotal,
            'effective_completed' => $effectiveCompleted,
            'original_submitted' => $originalSubmitted,
            'original_remaining' => max(0, $effectiveTotal - $originalSubmitted),
            'approved_total' => min($approvedTotal, $effectiveTotal),
            'available_to_submit' => max(0, min($effectiveCompleted, $effectiveTotal) - $originalSubmitted),
            'unresolved_rework' => (int) $unresolvedReviews->sum('rework_count'),
            'unresolved_reviews' => $unresolvedReviews,
            'waiting_count' => $waitingCount,
            'reviewing_count' => $reviewingCount,
            'active_submission_count' => $waitingCount + $reviewingCount,
        ];
    }

    public function unresolvedReviews(Assignment $assignment): Collection
    {
        return QcReview::query()
            ->with([
                'submission:id,submission_code,assignment_id,submitted_count,submission_type',
                'issues.reason:id,name',
                'issues.section:id,name',
            ])
            ->whereNotNull('reviewed_at')
            ->where('rework_count', '>', 0)
            ->whereHas('submission', fn ($query) => $query->where('assignment_id', $assignment->id))
            ->whereDoesntHave('reworkSubmission')
            ->orderBy('reviewed_at')
            ->get();
    }

    public function recalculate(Assignment $assignment): Assignment
    {
        $assignment = Assignment::query()->findOrFail($assignment->id);

        if (in_array($assignment->status, [
            AssignmentStatus::CANCELLED,
            AssignmentStatus::DUPLICATE_REVIEW,
            AssignmentStatus::DUPLICATE_CONFIRMED,
        ], true)) {
            return $assignment;
        }

        $summary = $this->summary($assignment);

        $nextStatus = match (true) {
            $summary['approved_total'] >= $summary['effective_total']
                && $summary['unresolved_rework'] === 0
                && $summary['active_submission_count'] === 0
                => AssignmentStatus::COMPLETED,

            $summary['unresolved_rework'] > 0
                => AssignmentStatus::REWORK,

            $summary['original_submitted'] >= $summary['effective_total']
                && $summary['reviewing_count'] > 0
                => AssignmentStatus::QC_REVIEWING,

            $summary['original_submitted'] >= $summary['effective_total']
                && $summary['waiting_count'] > 0
                => AssignmentStatus::SUBMITTED_QC,

            $assignment->status === AssignmentStatus::PENDING && $assignment->started_at === null
                => AssignmentStatus::PENDING,

            default => AssignmentStatus::ONGOING,
        };

        $updates = ['status' => $nextStatus];

        if ($nextStatus === AssignmentStatus::COMPLETED && $assignment->completed_at === null) {
            $updates['completed_at'] = now();
        }

        $assignment->update($updates);

        return $assignment->fresh();
    }
}
