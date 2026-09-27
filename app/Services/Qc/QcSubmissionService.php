<?php

namespace App\Services\Qc;

use App\Enums\AssignmentStatus;
use App\Enums\AuditAction;
use App\Enums\QcSubmissionStatus;
use App\Enums\QcSubmissionType;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Assignment;
use App\Models\OfficeNotification;
use App\Models\QcReview;
use App\Models\QcSubmission;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

class QcSubmissionService
{
    public function __construct(
        private readonly AssignmentQcStateService $assignmentState,
        private readonly WorkOrderQcStateService $workOrderState,
        private readonly AuditLogger $audit,
    ) {}

    public function submitOriginal(
        Assignment $assignment,
        User $employee,
        int $count,
        string $idempotencyKey,
        ?string $scopeText = null,
        ?string $note = null,
    ): QcSubmission {
        if (! $employee->can('task.submit_qc')) {
            throw new DomainException('You do not have permission to submit work for QC.');
        }

        $submission = DB::transaction(function () use (
            $assignment,
            $employee,
            $count,
            $idempotencyKey,
            $scopeText,
            $note
        ) {
            $locked = Assignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->employee_id !== $employee->id) {
                throw new DomainException('This assignment belongs to another employee.');
            }

            $existing = QcSubmission::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                if ($existing->assignment_id !== $locked->id || $existing->submitted_by !== $employee->id) {
                    throw new DomainException('This submission token has already been used.');
                }

                return $existing;
            }

            if ($locked->status !== AssignmentStatus::ONGOING) {
                throw new DomainException('Original work can only be submitted while the assignment is ongoing.');
            }

            $workOrder = $locked->workOrder()->firstOrFail();

            if ($workOrder->status === WorkOrderStatus::CANCELLED) {
                throw new DomainException('Cancelled work cannot be submitted for QC.');
            }

            $summary = $this->assignmentState->summary($locked);

            if ($count < 1) {
                throw new DomainException('Submit count must be at least 1.');
            }

            if ($count > $summary['available_to_submit']) {
                throw new DomainException('Submit count exceeds the completed work currently available for QC.');
            }

            $type = ($summary['original_submitted'] + $count) >= $summary['effective_total']
                ? QcSubmissionType::FINAL
                : QcSubmissionType::PARTIAL;

            $submission = QcSubmission::query()->create([
                'submission_code' => null,
                'idempotency_key' => $idempotencyKey,
                'assignment_id' => $locked->id,
                'submitted_by' => $employee->id,
                'submission_type' => $type,
                'submitted_count' => $count,
                'scope_text' => $scopeText,
                'employee_note' => $note,
                'source_review_id' => null,
                'status' => QcSubmissionStatus::WAITING,
                'submitted_at' => now(),
            ]);

            $submission->update([
                'submission_code' => sprintf('QCS-%06d', $submission->id),
            ]);

            $this->audit->log(
                AuditAction::QC_SUBMITTED->value,
                $submission,
                oldValues: null,
                newValues: [
                    'assignment_id' => $locked->id,
                    'submission_type' => $type->value,
                    'submitted_count' => $count,
                    'status' => QcSubmissionStatus::WAITING->value,
                ],
            );

            $this->assignmentState->recalculate($locked);
            $this->workOrderState->recalculate($workOrder);

            return $submission->fresh();
        });

        $this->notifyMatchingQc($submission->id);

        return $submission;
    }

    public function submitRework(
        Assignment $assignment,
        QcReview $sourceReview,
        User $employee,
        string $idempotencyKey,
        ?string $note = null,
    ): QcSubmission {
        if (! $employee->can('task.submit_qc')) {
            throw new DomainException('You do not have permission to submit rework for QC.');
        }

        $submission = DB::transaction(function () use (
            $assignment,
            $sourceReview,
            $employee,
            $idempotencyKey,
            $note
        ) {
            $lockedAssignment = Assignment::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedReview = QcReview::query()
                ->whereKey($sourceReview->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAssignment->employee_id !== $employee->id) {
                throw new DomainException('This assignment belongs to another employee.');
            }

            if (in_array($lockedAssignment->status, [
                AssignmentStatus::CANCELLED,
                AssignmentStatus::DUPLICATE_REVIEW,
                AssignmentStatus::DUPLICATE_CONFIRMED,
                AssignmentStatus::COMPLETED,
            ], true)) {
                throw new DomainException('This assignment is already closed or in duplicate review and cannot receive a rework submission.');
            }

            $existing = QcSubmission::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                if ($existing->assignment_id !== $lockedAssignment->id || $existing->submitted_by !== $employee->id) {
                    throw new DomainException('This submission token has already been used.');
                }

                return $existing;
            }

            $sourceSubmission = $lockedReview->submission()->firstOrFail();

            if ($sourceSubmission->assignment_id !== $lockedAssignment->id) {
                throw new DomainException('The selected QC review does not belong to this assignment.');
            }

            $lockedReview->load('latestOverride');

            if ($lockedReview->reviewed_at === null || $lockedReview->effectiveReworkCount() < 1) {
                throw new DomainException('This QC review does not have unresolved rework.');
            }

            if (QcSubmission::query()->where('source_review_id', $lockedReview->id)->exists()) {
                throw new DomainException('This rework has already been resubmitted.');
            }

            $submission = QcSubmission::query()->create([
                'submission_code' => null,
                'idempotency_key' => $idempotencyKey,
                'assignment_id' => $lockedAssignment->id,
                'submitted_by' => $employee->id,
                'submission_type' => QcSubmissionType::REWORK,
                'submitted_count' => $lockedReview->effectiveReworkCount(),
                'scope_text' => null,
                'employee_note' => $note,
                'source_review_id' => $lockedReview->id,
                'status' => QcSubmissionStatus::WAITING,
                'submitted_at' => now(),
            ]);

            $submission->update([
                'submission_code' => sprintf('QCS-%06d', $submission->id),
            ]);

            $this->audit->log(
                AuditAction::QC_REWORK_SUBMITTED->value,
                $submission,
                oldValues: null,
                newValues: [
                    'assignment_id' => $lockedAssignment->id,
                    'source_review_id' => $lockedReview->id,
                    'submitted_count' => $submission->submitted_count,
                    'status' => QcSubmissionStatus::WAITING->value,
                ],
            );

            $this->assignmentState->recalculate($lockedAssignment);
            $this->workOrderState->recalculate($lockedAssignment->workOrder()->firstOrFail());

            return $submission->fresh();
        });

        $this->notifyMatchingQc($submission->id);

        return $submission;
    }

    private function notifyMatchingQc(int $submissionId): void
    {
        try {
            $submission = QcSubmission::query()
                ->with(['assignment.workOrder.client', 'submitter'])
                ->findOrFail($submissionId);

            $scope = $submission->assignment->workOrder->work_type->value;

            $qcUsers = User::query()
                ->role(RoleName::QC->value)
                ->where('status', UserStatus::ACTIVE->value)
                ->whereHas('qcScopes', fn ($query) => $query
                    ->whereNull('revoked_at')
                    ->where('scope', $scope))
                ->get()
                ->filter(fn (User $qc) => $qc->can('qc.review'));

            foreach ($qcUsers as $qc) {
                OfficeNotification::query()->create([
                    'user_id' => $qc->id,
                    'type' => $submission->submission_type === QcSubmissionType::REWORK
                        ? 'QC_REWORK_RESUBMITTED'
                        : 'QC_SUBMITTED',
                    'title' => $submission->submission_type === QcSubmissionType::REWORK
                        ? "{$submission->submission_code} rework resubmitted"
                        : "{$submission->submission_code} waiting for QC",
                    'message' => "{$submission->submitter->name} submitted {$submission->submitted_count} item(s) for {$submission->assignment->workOrder->work_code} / {$submission->assignment->assignment_code}.",
                    'related_type' => QcSubmission::class,
                    'related_id' => $submission->id,
                    'requires_action' => false,
                ]);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
