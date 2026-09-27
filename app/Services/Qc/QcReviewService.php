<?php

namespace App\Services\Qc;

use App\Enums\AssignmentStatus;
use App\Enums\AuditAction;
use App\Enums\QcReasonType;
use App\Enums\QcReviewResult;
use App\Enums\QcSubmissionStatus;
use App\Enums\RoleName;
use App\Models\Assignment;
use App\Models\OfficeNotification;
use App\Models\QcReason;
use App\Models\QcReview;
use App\Models\QcReviewBonusItem;
use App\Models\QcReviewIssue;
use App\Models\QcReviewLockEvent;
use App\Models\QcSubmission;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QcReviewService
{
    public function __construct(
        private readonly AssignmentQcStateService $assignmentState,
        private readonly WorkOrderQcStateService $workOrderState,
        private readonly AuditLogger $audit,
    ) {}

    public function startReview(QcSubmission $submission, User $qc): QcReview
    {
        $this->assertQcPermission($qc);

        return DB::transaction(function () use ($submission, $qc) {
            $lockedSubmission = QcSubmission::query()
                ->whereKey($submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            $assignment = Assignment::query()
                ->whereKey($lockedSubmission->assignment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertScope($qc, $assignment);
            $this->assertAssignmentReviewable($assignment);

            $existingReview = QcReview::query()
                ->where('qc_submission_id', $lockedSubmission->id)
                ->lockForUpdate()
                ->first();

            if ($lockedSubmission->status === QcSubmissionStatus::REVIEWING
                && $existingReview
                && $existingReview->reviewer_id === $qc->id
                && $existingReview->released_at === null) {
                return $existingReview;
            }

            if ($lockedSubmission->status !== QcSubmissionStatus::WAITING) {
                throw new DomainException('This submission is no longer waiting for QC.');
            }

            if ($existingReview && $existingReview->reviewed_at !== null) {
                throw new DomainException('This QC submission has already been completed.');
            }

            if ($existingReview && $existingReview->released_at === null) {
                throw new DomainException('This submission is already being reviewed.');
            }

            if ($existingReview) {
                $previousReviewerId = $existingReview->reviewer_id;

                $existingReview->update([
                    'reviewer_id' => $qc->id,
                    'responsible_employee_id' => $assignment->employee_id,
                    'started_at' => now(),
                    'released_at' => null,
                    'released_by' => null,
                    'release_reason' => null,
                ]);

                $review = $existingReview->fresh();

                QcReviewLockEvent::query()->create([
                    'qc_review_id' => $review->id,
                    'event_type' => 'REACQUIRED',
                    'reviewer_id' => $qc->id,
                    'actor_id' => $qc->id,
                    'note' => "Previous reviewer user ID: {$previousReviewerId}.",
                    'created_at' => now(),
                ]);

                $this->audit->log(
                    AuditAction::QC_REVIEW_LOCK_REACQUIRED->value,
                    $review,
                    oldValues: ['reviewer_id' => $previousReviewerId, 'submission_status' => QcSubmissionStatus::WAITING->value],
                    newValues: ['reviewer_id' => $qc->id, 'submission_status' => QcSubmissionStatus::REVIEWING->value],
                );
            } else {
                $review = QcReview::query()->create([
                    'review_code' => null,
                    'qc_submission_id' => $lockedSubmission->id,
                    'reviewer_id' => $qc->id,
                    'responsible_employee_id' => $assignment->employee_id,
                    'approved_count' => null,
                    'rework_count' => null,
                    'result' => null,
                    'bonus_points' => 0,
                    'negative_points' => 0,
                    'is_major_error' => false,
                    'review_comment' => null,
                    'started_at' => now(),
                    'reviewed_at' => null,
                    'major_error_email_sent' => false,
                    'major_error_email_sent_at' => null,
                ]);

                $review->update([
                    'review_code' => sprintf('QC-%06d', $review->id),
                ]);

                QcReviewLockEvent::query()->create([
                    'qc_review_id' => $review->id,
                    'event_type' => 'ACQUIRED',
                    'reviewer_id' => $qc->id,
                    'actor_id' => $qc->id,
                    'created_at' => now(),
                ]);

                $this->audit->log(
                    AuditAction::QC_REVIEW_STARTED->value,
                    $review,
                    oldValues: ['submission_status' => QcSubmissionStatus::WAITING->value],
                    newValues: [
                        'submission_status' => QcSubmissionStatus::REVIEWING->value,
                        'reviewer_id' => $qc->id,
                    ],
                );
            }

            $lockedSubmission->update([
                'status' => QcSubmissionStatus::REVIEWING,
            ]);

            $this->assignmentState->recalculate($assignment);
            $this->workOrderState->recalculate($assignment->workOrder()->firstOrFail());

            return $review->fresh();
        });
    }

    public function releaseReview(QcSubmission $submission, User $actor, ?string $reason = null): QcReview
    {
        return DB::transaction(function () use ($submission, $actor, $reason) {
            $lockedSubmission = QcSubmission::query()
                ->whereKey($submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedSubmission->status !== QcSubmissionStatus::REVIEWING) {
                throw new DomainException('Only an active QC review lock can be released.');
            }

            $assignment = Assignment::query()
                ->whereKey($lockedSubmission->assignment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $review = QcReview::query()
                ->where('qc_submission_id', $lockedSubmission->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($review->reviewed_at !== null || $review->released_at !== null) {
                throw new DomainException('This QC review lock is no longer active.');
            }

            $actorIsOwner = $review->reviewer_id === $actor->id;
            $actorIsSuperAdmin = $actor->hasRole(RoleName::SUPER_ADMIN->value);
            $actorIsQc = $actor->hasRole(RoleName::QC->value);

            if ($actorIsQc) {
                $this->assertQcPermission($actor);
                $this->assertScope($actor, $assignment);
            }

            $staleMinutes = max(5, (int) config('office.qc.review_lock_minutes', 120));
            $isStale = $review->started_at?->lte(now()->subMinutes($staleMinutes)) ?? false;

            if (! $actorIsOwner && ! $actorIsSuperAdmin && ! ($actorIsQc && $isStale)) {
                throw new DomainException(
                    "Another QC reviewer owns this lock. It can be recovered after {$staleMinutes} minutes or by Super Admin."
                );
            }

            $releaseReason = filled($reason)
                ? trim((string) $reason)
                : ($actorIsOwner ? 'Released by the reviewing QC user.' : 'Recovered stale QC review lock.');

            $review->update([
                'released_at' => now(),
                'released_by' => $actor->id,
                'release_reason' => $releaseReason,
            ]);

            $lockedSubmission->update([
                'status' => QcSubmissionStatus::WAITING,
            ]);

            QcReviewLockEvent::query()->create([
                'qc_review_id' => $review->id,
                'event_type' => 'RELEASED',
                'reviewer_id' => $review->reviewer_id,
                'actor_id' => $actor->id,
                'note' => $releaseReason,
                'created_at' => now(),
            ]);

            $this->audit->log(
                AuditAction::QC_REVIEW_LOCK_RELEASED->value,
                $review,
                oldValues: [
                    'submission_status' => QcSubmissionStatus::REVIEWING->value,
                    'reviewer_id' => $review->reviewer_id,
                ],
                newValues: [
                    'submission_status' => QcSubmissionStatus::WAITING->value,
                    'released_by' => $actor->id,
                    'release_reason' => $releaseReason,
                ],
            );

            $this->assignmentState->recalculate($assignment);
            $this->workOrderState->recalculate($assignment->workOrder()->firstOrFail());

            return $review->fresh();
        });
    }

    public function finishReview(QcSubmission $submission, User $qc, array $data): QcReview
    {
        $this->assertQcPermission($qc);

        $review = DB::transaction(function () use ($submission, $qc, $data) {
            $lockedSubmission = QcSubmission::query()
                ->whereKey($submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            $assignment = Assignment::query()
                ->whereKey($lockedSubmission->assignment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $workOrder = $assignment->workOrder()
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertScope($qc, $assignment);
            $this->assertAssignmentReviewable($assignment);

            if ($lockedSubmission->status !== QcSubmissionStatus::REVIEWING) {
                throw new DomainException('Only a submission currently under review can be finished.');
            }

            $review = QcReview::query()
                ->where('qc_submission_id', $lockedSubmission->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($review->reviewer_id !== $qc->id) {
                throw new DomainException('This submission is locked by another QC reviewer.');
            }

            if ($review->released_at !== null) {
                throw new DomainException('This QC review lock was released. Start the review again before finishing.');
            }

            if ($review->reviewed_at !== null) {
                throw new DomainException('This QC review has already been completed.');
            }

            $approvedCount = (int) ($data['approved_count'] ?? -1);
            $reworkCount = (int) ($data['rework_count'] ?? -1);

            if ($approvedCount < 0 || $reworkCount < 0) {
                throw new DomainException('Approved and rework counts cannot be negative.');
            }

            if (($approvedCount + $reworkCount) !== (int) $lockedSubmission->submitted_count) {
                throw new DomainException('Approved count plus rework count must equal the submitted count.');
            }

            $issues = $this->normalizeIssues($data['issues'] ?? []);
            $bonusItems = $this->normalizeBonusItems($data['bonus_items'] ?? []);

            if ($reworkCount > 0 && $issues->isEmpty()) {
                throw new DomainException('At least one QC issue/reason is required when rework is requested.');
            }

            $negativeTotal = (int) $issues->sum('negative_points');
            $bonusTotal = (int) $bonusItems->sum('points');
            $isMajorError = (bool) ($data['is_major_error'] ?? false);

            if ($negativeTotal > 5) {
                throw new DomainException('Total negative points cannot exceed 5 for one QC review.');
            }

            if ($bonusTotal > 5) {
                throw new DomainException('Total bonus points cannot exceed 5 for one QC review.');
            }

            if ($isMajorError && $negativeTotal < 1) {
                throw new DomainException('Major Error requires at least 1 negative point.');
            }

            foreach ($issues as $issue) {
                QcReviewIssue::query()->create([
                    'qc_review_id' => $review->id,
                    'negative_reason_id' => $issue['negative_reason_id'],
                    'section_id' => $issue['section_id'],
                    'site_name' => $issue['site_name'],
                    'negative_points' => $issue['negative_points'],
                    'comment' => $issue['comment'],
                ]);
            }

            foreach ($bonusItems as $bonusItem) {
                QcReviewBonusItem::query()->create([
                    'qc_review_id' => $review->id,
                    'bonus_reason_id' => $bonusItem['bonus_reason_id'],
                    'points' => $bonusItem['points'],
                    'comment' => $bonusItem['comment'],
                ]);
            }

            $result = $reworkCount > 0
                ? QcReviewResult::REWORK_REQUIRED
                : QcReviewResult::APPROVED;

            $review->update([
                'approved_count' => $approvedCount,
                'rework_count' => $reworkCount,
                'result' => $result,
                'bonus_points' => $bonusTotal,
                'negative_points' => $negativeTotal,
                'is_major_error' => $isMajorError,
                'review_comment' => $data['review_comment'] ?? null,
                'reviewed_at' => now(),
            ]);

            $lockedSubmission->update([
                'status' => QcSubmissionStatus::REVIEWED,
            ]);

            $this->audit->log(
                AuditAction::QC_REVIEW_COMPLETED->value,
                $review,
                oldValues: [
                    'result' => null,
                    'submission_status' => QcSubmissionStatus::REVIEWING->value,
                ],
                newValues: [
                    'result' => $result->value,
                    'approved_count' => $approvedCount,
                    'rework_count' => $reworkCount,
                    'bonus_points' => $bonusTotal,
                    'negative_points' => $negativeTotal,
                    'is_major_error' => $isMajorError,
                    'submission_status' => QcSubmissionStatus::REVIEWED->value,
                ],
            );

            $this->assignmentState->recalculate($assignment);
            $this->workOrderState->recalculate($workOrder);

            return $review->fresh([
                'submission.assignment.workOrder.client',
                'responsibleEmployee.primaryTeam.teamLeader',
                'issues.reason',
                'bonusItems.reason',
            ]);
        });

        $this->notifyReviewResult($review->id);

        return $review;
    }

    private function normalizeIssues(array $rows): Collection
    {
        return collect($rows)
            ->filter(fn ($row) => ! empty($row['reason_id']))
            ->map(function ($row) {
                $reason = QcReason::query()->find($row['reason_id']);

                if (! $reason
                    || $reason->type !== QcReasonType::NEGATIVE
                    || ! $reason->is_active) {
                    throw new DomainException('Select a valid active negative QC reason.');
                }

                $points = (int) ($row['negative_points'] ?? 0);

                if ($points < 0 || $points > 5) {
                    throw new DomainException('Each negative issue must use 0 to 5 points.');
                }

                return [
                    'negative_reason_id' => $reason->id,
                    'section_id' => ! empty($row['section_id']) ? (int) $row['section_id'] : null,
                    'site_name' => filled($row['site_name'] ?? null) ? trim((string) $row['site_name']) : null,
                    'negative_points' => $points,
                    'comment' => filled($row['comment'] ?? null) ? trim((string) $row['comment']) : null,
                ];
            })
            ->values();
    }

    private function normalizeBonusItems(array $rows): Collection
    {
        return collect($rows)
            ->filter(fn ($row) => ! empty($row['reason_id']))
            ->map(function ($row) {
                $reason = QcReason::query()->find($row['reason_id']);

                if (! $reason
                    || $reason->type !== QcReasonType::BONUS
                    || ! $reason->is_active) {
                    throw new DomainException('Select a valid active bonus QC reason.');
                }

                $points = (int) ($row['points'] ?? 0);

                if ($points < 1 || $points > 5) {
                    throw new DomainException('Each bonus item must use 1 to 5 points.');
                }

                return [
                    'bonus_reason_id' => $reason->id,
                    'points' => $points,
                    'comment' => filled($row['comment'] ?? null) ? trim((string) $row['comment']) : null,
                ];
            })
            ->values();
    }

    private function assertQcPermission(User $qc): void
    {
        if (! $qc->can('qc.review')) {
            throw new DomainException('You do not have permission to perform QC reviews.');
        }
    }

    private function assertScope(User $qc, Assignment $assignment): void
    {
        $scope = $assignment->workOrder()->value('work_type');

        $hasScope = $qc->qcScopes()
            ->whereNull('revoked_at')
            ->where('scope', $scope)
            ->exists();

        if (! $hasScope) {
            throw new DomainException('This submission is outside your active QC scope.');
        }
    }

    private function assertAssignmentReviewable(Assignment $assignment): void
    {
        if (in_array($assignment->status, [
            AssignmentStatus::CANCELLED,
            AssignmentStatus::DUPLICATE_REVIEW,
            AssignmentStatus::DUPLICATE_CONFIRMED,
            AssignmentStatus::COMPLETED,
        ], true)) {
            throw new DomainException('This assignment is already closed and cannot receive another QC decision.');
        }
    }

    private function notifyReviewResult(int $reviewId): void
    {
        try {
            $review = QcReview::query()
                ->with([
                    'submission.assignment.workOrder.client',
                    'responsibleEmployee.primaryTeam.teamLeader',
                ])
                ->findOrFail($reviewId);

            $submission = $review->submission;
            $assignment = $submission->assignment;
            $workOrder = $assignment->workOrder;
            $employee = $review->responsibleEmployee;

            $recipients = collect([
                $employee->id,
                $employee->primaryTeam?->teamLeader?->id,
                $workOrder->created_by,
            ])->filter()->map(fn ($id) => (int) $id)->unique();

            $title = $review->is_major_error
                ? "{$review->review_code} Major Error"
                : ($review->result === QcReviewResult::APPROVED
                    ? "{$review->review_code} approved"
                    : "{$review->review_code} requires rework");

            $message = "{$submission->submission_code}: {$review->approved_count} approved"
                .($review->rework_count > 0 ? ", {$review->rework_count} rework" : '')
                .". Negative {$review->negative_points}/5, Bonus {$review->bonus_points}/5."
                .($review->is_major_error ? ' Major Error email is required from the reviewing QC user.' : '');

            foreach ($recipients as $recipientId) {
                OfficeNotification::query()->create([
                    'user_id' => $recipientId,
                    'type' => $review->is_major_error
                        ? 'QC_MAJOR_ERROR'
                        : ($review->result === QcReviewResult::APPROVED
                            ? 'QC_APPROVED'
                            : 'QC_REWORK_REQUIRED'),
                    'title' => $title,
                    'message' => $message,
                    'related_type' => QcReview::class,
                    'related_id' => $review->id,
                    'requires_action' => $review->is_major_error,
                ]);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
