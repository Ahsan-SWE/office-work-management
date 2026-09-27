<?php

namespace App\Services\Qc;

use App\Enums\AuditAction;
use App\Enums\QcReviewResult;
use App\Enums\RoleName;
use App\Models\Assignment;
use App\Models\OfficeNotification;
use App\Models\QcReview;
use App\Models\QcReviewEscalation;
use App\Models\QcReviewOverride;
use App\Models\QcSubmission;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Audit\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

class QcEscalationService
{
    public function __construct(
        private readonly AssignmentQcStateService $assignmentState,
        private readonly WorkOrderQcStateService $workOrderState,
        private readonly AuditLogger $audit,
    ) {}

    public function escalate(QcReview $review, User $teamLeader, string $reason): QcReviewEscalation
    {
        if (! $teamLeader->hasRole(RoleName::TEAM_LEADER->value)) {
            throw new DomainException('Only a Team Leader can create a QC appeal.');
        }

        $review->loadMissing('responsibleEmployee.primaryTeam');

        /** @var User $employee */
        $employee = $review->responsibleEmployee;
        /** @var Team|null $team */
        $team = $employee->primaryTeam;

        if ($review->reviewed_at === null) {
            throw new DomainException('Only a completed QC review can be appealed.');
        }

        if ($team?->team_leader_id !== $teamLeader->id) {
            throw new DomainException('You can only appeal QC reviews for employees in your team.');
        }

        $escalation = DB::transaction(function () use ($review, $teamLeader, $reason) {
            $lockedReview = QcReview::query()->whereKey($review->id)->lockForUpdate()->firstOrFail();

            if (QcReviewEscalation::query()->where('qc_review_id', $lockedReview->id)->exists()) {
                throw new DomainException('This QC review has already been escalated.');
            }

            $escalation = QcReviewEscalation::query()->create([
                'qc_review_id' => $lockedReview->id,
                'raised_by' => $teamLeader->id,
                'reason' => trim($reason),
                'status' => 'OPEN',
                'opened_at' => now(),
            ]);

            $this->audit->log(
                AuditAction::QC_ESCALATION_CREATED->value,
                $escalation,
                oldValues: null,
                newValues: [
                    'qc_review_id' => $lockedReview->id,
                    'raised_by' => $teamLeader->id,
                    'status' => 'OPEN',
                ],
            );

            return $escalation;
        });

        $this->notifySuperAdmins($review, $escalation);

        return $escalation;
    }

    public function resolve(QcReviewEscalation $escalation, User $superAdmin, string $note): QcReviewEscalation
    {
        $this->assertSuperAdmin($superAdmin);

        return DB::transaction(function () use ($escalation, $superAdmin, $note) {
            $locked = QcReviewEscalation::query()
                ->whereKey($escalation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'OPEN') {
                throw new DomainException('This QC escalation is already resolved.');
            }

            $locked->update([
                'status' => 'RESOLVED',
                'resolved_by' => $superAdmin->id,
                'resolution_note' => trim($note),
                'resolved_at' => now(),
            ]);

            $this->audit->log(
                AuditAction::QC_ESCALATION_RESOLVED->value,
                $locked,
                oldValues: ['status' => 'OPEN'],
                newValues: [
                    'status' => 'RESOLVED',
                    'resolved_by' => $superAdmin->id,
                    'resolution_note' => trim($note),
                ],
            );

            return $locked->fresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function override(QcReview $review, User $superAdmin, array $data): QcReviewOverride
    {
        $this->assertSuperAdmin($superAdmin);

        $override = DB::transaction(function () use ($review, $superAdmin, $data) {
            $lockedReview = QcReview::query()
                ->whereKey($review->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedReview->loadMissing(['submission', 'latestOverride']);

            /** @var QcSubmission $submission */
            $submission = $lockedReview->submission;

            $openEscalation = QcReviewEscalation::query()
                ->where('qc_review_id', $lockedReview->id)
                ->where('status', 'OPEN')
                ->lockForUpdate()
                ->first();

            if ($openEscalation === null) {
                throw new DomainException('Only an open QC escalation can be overridden.');
            }

            if ($lockedReview->reviewed_at === null) {
                throw new DomainException('Only a completed QC review can be overridden.');
            }

            $approved = (int) $data['approved_count'];
            $rework = (int) $data['rework_count'];
            $negative = (int) $data['negative_points'];
            $bonus = (int) $data['bonus_points'];
            $major = (bool) ($data['is_major_error'] ?? false);

            if ($approved < 0 || $rework < 0 || ($approved + $rework) !== (int) $submission->submitted_count) {
                throw new DomainException('Override approved + rework counts must equal the submitted count.');
            }

            if ($negative < 0 || $negative > 5 || $bonus < 0 || $bonus > 5) {
                throw new DomainException('Override negative and bonus points must each be between 0 and 5.');
            }

            if ($major && $negative < 1) {
                throw new DomainException('A Major Error override requires at least 1 negative point.');
            }

            if ($lockedReview->reworkSubmission()->exists()
                && (
                    $approved !== $lockedReview->effectiveApprovedCount()
                    || $rework !== $lockedReview->effectiveReworkCount()
                )) {
                throw new DomainException(
                    'Approved/rework counts cannot be changed after the returned work has already been resubmitted.'
                );
            }

            $result = $rework > 0
                ? QcReviewResult::REWORK_REQUIRED
                : QcReviewResult::APPROVED;

            $override = QcReviewOverride::query()->create([
                'qc_review_id' => $lockedReview->id,
                'super_admin_id' => $superAdmin->id,
                'approved_count' => $approved,
                'rework_count' => $rework,
                'result' => $result,
                'negative_points' => $negative,
                'bonus_points' => $bonus,
                'is_major_error' => $major,
                'reason' => trim((string) $data['reason']),
            ]);

            $this->audit->log(
                AuditAction::QC_REVIEW_OVERRIDDEN->value,
                $override,
                oldValues: [
                    'approved_count' => $lockedReview->effectiveApprovedCount(),
                    'rework_count' => $lockedReview->effectiveReworkCount(),
                    'negative_points' => $lockedReview->effectiveNegativePoints(),
                    'bonus_points' => $lockedReview->effectiveBonusPoints(),
                    'is_major_error' => $lockedReview->effectiveIsMajorError(),
                ],
                newValues: [
                    'approved_count' => $approved,
                    'rework_count' => $rework,
                    'result' => $result->value,
                    'negative_points' => $negative,
                    'bonus_points' => $bonus,
                    'is_major_error' => $major,
                    'reason' => trim((string) $data['reason']),
                ],
            );

            /** @var Assignment $assignment */
            $assignment = $submission->assignment()->firstOrFail();
            /** @var WorkOrder $workOrder */
            $workOrder = $assignment->workOrder()->firstOrFail();

            $this->assignmentState->recalculate($assignment);
            $this->workOrderState->recalculate($workOrder);

            return $override;
        });

        $this->notifyOverride($review, $override);

        return $override;
    }

    private function assertSuperAdmin(User $user): void
    {
        if (! $user->hasRole(RoleName::SUPER_ADMIN->value)) {
            throw new DomainException('Only Super Admin can perform this action.');
        }
    }

    private function notifySuperAdmins(QcReview $review, QcReviewEscalation $escalation): void
    {
        try {
            User::query()
                ->role(RoleName::SUPER_ADMIN->value)
                ->where('status', 'ACTIVE')
                ->get()
                ->each(function (User $user) use ($review, $escalation) {
                    OfficeNotification::query()->create([
                        'user_id' => $user->id,
                        'type' => 'QC_ESCALATION',
                        'title' => "{$review->review_code} appealed by Team Leader",
                        'message' => mb_substr($escalation->reason, 0, 500),
                        'related_type' => QcReviewEscalation::class,
                        'related_id' => $escalation->id,
                        'requires_action' => true,
                    ]);
                });
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function notifyOverride(QcReview $review, QcReviewOverride $override): void
    {
        try {
            $review->loadMissing('responsibleEmployee.primaryTeam.teamLeader');

            /** @var User $employee */
            $employee = $review->responsibleEmployee;
            /** @var Team|null $team */
            $team = $employee->primaryTeam;
            /** @var User|null $teamLeader */
            $teamLeader = $team?->teamLeader()->first();

            $result = $override->getAttribute('result');
            $resultValue = $result instanceof QcReviewResult ? $result->value : (string) $result;

            collect([
                $review->reviewer_id,
                $review->responsible_employee_id,
                $teamLeader?->id,
            ])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->each(function (int $userId) use ($review, $override, $resultValue) {
                    OfficeNotification::query()->create([
                        'user_id' => $userId,
                        'type' => 'QC_OVERRIDE',
                        'title' => "{$review->review_code} received a Super Admin override",
                        'message' => "Effective decision: {$resultValue}; negative {$override->negative_points}/5; bonus {$override->bonus_points}/5.",
                        'related_type' => QcReviewOverride::class,
                        'related_id' => $override->id,
                        'requires_action' => false,
                    ]);
                });
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
