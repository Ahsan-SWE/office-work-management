<?php

namespace App\Services\Performance;

use App\Enums\ImprovementSessionEventType;
use App\Enums\ImprovementSessionEventVisibility;
use App\Enums\ImprovementSessionStatus;
use App\Enums\PerformanceRating;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\EmployeeMonthlyQualityPerformance;
use App\Models\ImprovementSession;
use App\Models\OfficeNotification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class ImprovementSessionService
{
    public function reconcileEmployeeMonth(int $employeeId, CarbonInterface $month): ?ImprovementSession
    {
        $monthStart = $this->normalizeMonth($month);

        return DB::transaction(function () use ($employeeId, $monthStart): ?ImprovementSession {
            /** @var EmployeeMonthlyQualityPerformance|null $performance */
            $performance = EmployeeMonthlyQualityPerformance::query()
                ->where('employee_id', $employeeId)
                ->whereDate('performance_month', $monthStart->toDateString())
                ->lockForUpdate()
                ->first();

            /** @var ImprovementSession|null $session */
            $session = ImprovementSession::query()
                ->where('employee_id', $employeeId)
                ->whereDate('performance_month', $monthStart->toDateString())
                ->lockForUpdate()
                ->first();

            if ($performance === null || $this->performanceRating($performance) !== PerformanceRating::POOR) {
                if (
                    $session !== null
                    && $this->sessionStatus($session) !== ImprovementSessionStatus::CANCELLED_BY_RECALCULATION
                ) {
                    $from = $this->sessionStatus($session);
                    $session->update([
                        'status' => ImprovementSessionStatus::CANCELLED_BY_RECALCULATION,
                        'cancelled_at' => now(),
                    ]);

                    $this->appendEvent(
                        $session,
                        ImprovementSessionEventType::CANCELLED_BY_RECALCULATION,
                        null,
                        $from,
                        ImprovementSessionStatus::CANCELLED_BY_RECALCULATION,
                        'Monthly quality no longer qualifies as Poor.',
                        ImprovementSessionEventVisibility::EMPLOYEE,
                    );

                    $this->completeRelatedNotificationActions($session);
                }

                $this->ensureRepeatedPoorAlertForMonth(
                    $employeeId,
                    $monthStart->addMonth(),
                );

                return $session?->fresh(['employee', 'events']);
            }

            $employee = User::query()->findOrFail($employeeId);

            if ($session === null) {
                $session = ImprovementSession::query()->create([
                    'employee_id' => $employeeId,
                    'performance_month' => $monthStart,
                    'trigger_negative_points' => $performance->negative_points,
                    'trigger_bonus_points' => $performance->bonus_points,
                    'trigger_rating' => PerformanceRating::POOR,
                    'trigger_team_id' => $employee->primary_team_id,
                    'status' => ImprovementSessionStatus::OPEN,
                    'opened_at' => now(),
                ]);

                $this->appendEvent(
                    $session,
                    ImprovementSessionEventType::CREATED_FROM_POOR,
                    null,
                    null,
                    ImprovementSessionStatus::OPEN,
                    'Monthly quality reached Poor and created an Improvement Session.',
                    ImprovementSessionEventVisibility::EMPLOYEE,
                    [
                        'negative_points' => $performance->negative_points,
                        'bonus_points' => $performance->bonus_points,
                    ],
                );
            } elseif ($this->sessionStatus($session) === ImprovementSessionStatus::CANCELLED_BY_RECALCULATION) {
                $session->update([
                    'trigger_negative_points' => $performance->negative_points,
                    'trigger_bonus_points' => $performance->bonus_points,
                    'trigger_rating' => PerformanceRating::POOR,
                    'trigger_team_id' => $employee->primary_team_id,
                    'status' => ImprovementSessionStatus::OPEN,
                    'opened_at' => now(),
                    'started_at' => null,
                    'completed_at' => null,
                    'cancelled_at' => null,
                ]);

                $this->appendEvent(
                    $session,
                    ImprovementSessionEventType::REOPENED_BY_RECALCULATION,
                    null,
                    ImprovementSessionStatus::CANCELLED_BY_RECALCULATION,
                    ImprovementSessionStatus::OPEN,
                    'The same month became Poor again; the existing session was reopened.',
                    ImprovementSessionEventVisibility::EMPLOYEE,
                    [
                        'negative_points' => $performance->negative_points,
                        'bonus_points' => $performance->bonus_points,
                    ],
                );
            }

            $this->ensureRepeatedPoorAlert($session);
            $this->ensureRepeatedPoorAlertForMonth(
                $employeeId,
                $monthStart->addMonth(),
            );

            return $session->fresh(['employee', 'events']);
        });
    }

    public function reconcileExistingSessionsAgainstProjections(): void
    {
        ImprovementSession::query()
            ->select(['id', 'employee_id', 'performance_month'])
            ->orderBy('id')
            ->chunkById(250, function ($sessions): void {
                foreach ($sessions as $session) {
                    /** @var ImprovementSession $session */
                    $this->reconcileEmployeeMonth(
                        (int) $session->employee_id,
                        CarbonImmutable::parse((string) $session->performance_month),
                    );
                }
            });
    }

    public function start(ImprovementSession $session, User $actor): ImprovementSession
    {
        if ($this->sessionStatus($session) !== ImprovementSessionStatus::OPEN) {
            throw new DomainException('Only an open Improvement Session can be started.');
        }

        $session->update([
            'status' => ImprovementSessionStatus::IN_PROGRESS,
            'started_at' => now(),
            'updated_by_user_id' => $actor->id,
        ]);

        $this->appendEvent(
            $session,
            ImprovementSessionEventType::STARTED,
            $actor,
            ImprovementSessionStatus::OPEN,
            ImprovementSessionStatus::IN_PROGRESS,
            'Improvement Session started.',
            ImprovementSessionEventVisibility::EMPLOYEE,
        );

        return $session->fresh();
    }

    /**
     * @param array{
     *   improvement_plan?: string|null,
     *   team_leader_notes?: string|null,
     *   employee_visible_notes?: string|null,
     *   follow_up_date?: string|null
     * } $data
     */
    public function updateDetails(ImprovementSession $session, array $data, User $actor): ImprovementSession
    {
        $currentStatus = $this->sessionStatus($session);

        if (! in_array($currentStatus, [
            ImprovementSessionStatus::OPEN,
            ImprovementSessionStatus::IN_PROGRESS,
        ], true)) {
            throw new DomainException('Completed or cancelled Improvement Sessions are read-only.');
        }

        $before = [
            'improvement_plan' => $session->improvement_plan,
            'team_leader_notes' => $session->team_leader_notes,
            'employee_visible_notes' => $session->employee_visible_notes,
            'follow_up_date' => $this->dateString($session->getAttribute('follow_up_date')),
        ];

        $session->fill([
            'improvement_plan' => $data['improvement_plan'] ?? null,
            'team_leader_notes' => $data['team_leader_notes'] ?? null,
            'employee_visible_notes' => $data['employee_visible_notes'] ?? null,
            'follow_up_date' => $data['follow_up_date'] ?? null,
            'updated_by_user_id' => $actor->id,
        ]);
        $session->save();

        $after = [
            'improvement_plan' => $session->improvement_plan,
            'team_leader_notes' => $session->team_leader_notes,
            'employee_visible_notes' => $session->employee_visible_notes,
            'follow_up_date' => $this->dateString($session->getAttribute('follow_up_date')),
        ];

        $employeeChanged = [];
        foreach (['improvement_plan', 'employee_visible_notes', 'follow_up_date'] as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $employeeChanged[] = $field;
            }
        }

        if ($employeeChanged !== []) {
            $eventType = in_array('follow_up_date', $employeeChanged, true)
                ? ImprovementSessionEventType::FOLLOW_UP_CHANGED
                : ImprovementSessionEventType::PLAN_UPDATED;

            $this->appendEvent(
                $session,
                $eventType,
                $actor,
                $currentStatus,
                $currentStatus,
                'Employee-visible Improvement Session details updated.',
                ImprovementSessionEventVisibility::EMPLOYEE,
                ['changed_fields' => $employeeChanged],
            );
        }

        if ($before['team_leader_notes'] !== $after['team_leader_notes']) {
            $this->appendEvent(
                $session,
                ImprovementSessionEventType::NOTE_ADDED,
                $actor,
                $currentStatus,
                $currentStatus,
                'Internal Team Leader / Super Admin notes updated.',
                ImprovementSessionEventVisibility::INTERNAL,
                ['changed_fields' => ['team_leader_notes']],
            );
        }

        return $session->fresh();
    }

    public function complete(ImprovementSession $session, User $actor): ImprovementSession
    {
        if ($this->sessionStatus($session) !== ImprovementSessionStatus::IN_PROGRESS) {
            throw new DomainException('Only an in-progress Improvement Session can be completed.');
        }

        $session->update([
            'status' => ImprovementSessionStatus::COMPLETED,
            'completed_at' => now(),
            'updated_by_user_id' => $actor->id,
        ]);

        $this->appendEvent(
            $session,
            ImprovementSessionEventType::COMPLETED,
            $actor,
            ImprovementSessionStatus::IN_PROGRESS,
            ImprovementSessionStatus::COMPLETED,
            'Improvement Session completed.',
            ImprovementSessionEventVisibility::EMPLOYEE,
        );

        $this->completeRelatedNotificationActions($session);

        return $session->fresh();
    }

    private function ensureRepeatedPoorAlertForMonth(int $employeeId, CarbonInterface $month): void
    {
        $monthStart = $this->normalizeMonth($month);

        /** @var ImprovementSession|null $session */
        $session = ImprovementSession::query()
            ->where('employee_id', $employeeId)
            ->whereDate('performance_month', $monthStart->toDateString())
            ->first();

        if (
            $session === null
            || $this->sessionStatus($session) === ImprovementSessionStatus::CANCELLED_BY_RECALCULATION
        ) {
            return;
        }

        $this->ensureRepeatedPoorAlert($session);
    }

    private function ensureRepeatedPoorAlert(ImprovementSession $session): void
    {
        if ($session->repeated_poor_alerted_at !== null) {
            return;
        }

        $month = CarbonImmutable::parse((string) $session->performance_month)->startOfMonth();
        $previousMonth = $month->subMonth();

        /** @var EmployeeMonthlyQualityPerformance|null $current */
        $current = EmployeeMonthlyQualityPerformance::query()
            ->where('employee_id', $session->employee_id)
            ->whereDate('performance_month', $month->toDateString())
            ->first();

        /** @var EmployeeMonthlyQualityPerformance|null $previous */
        $previous = EmployeeMonthlyQualityPerformance::query()
            ->where('employee_id', $session->employee_id)
            ->whereDate('performance_month', $previousMonth->toDateString())
            ->first();

        if (
            $this->performanceRating($current) !== PerformanceRating::POOR
            || $this->performanceRating($previous) !== PerformanceRating::POOR
        ) {
            return;
        }

        $admins = User::role(RoleName::SUPER_ADMIN->value)
            ->where('status', UserStatus::ACTIVE->value)
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        $session->loadMissing('employee');

        foreach ($admins as $admin) {
            OfficeNotification::query()->firstOrCreate(
                [
                    'user_id' => $admin->id,
                    'type' => 'REPEATED_POOR',
                    'related_type' => ImprovementSession::class,
                    'related_id' => $session->id,
                ],
                [
                    'title' => 'Consecutive Poor monthly quality',
                    'message' => sprintf(
                        '%s was rated Poor in both %s and %s.',
                        $session->employee->name,
                        $previousMonth->format('F Y'),
                        $month->format('F Y'),
                    ),
                    'is_read' => false,
                    'requires_action' => true,
                ],
            );
        }

        $session->update(['repeated_poor_alerted_at' => now()]);

        $this->appendEvent(
            $session,
            ImprovementSessionEventType::REPEATED_POOR_ALERTED,
            null,
            $this->sessionStatus($session),
            $this->sessionStatus($session),
            'Super Admin was alerted about consecutive Poor months.',
            ImprovementSessionEventVisibility::INTERNAL,
            [
                'previous_month' => $previousMonth->toDateString(),
                'current_month' => $month->toDateString(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function appendEvent(
        ImprovementSession $session,
        ImprovementSessionEventType $eventType,
        ?User $actor,
        ?ImprovementSessionStatus $fromStatus,
        ?ImprovementSessionStatus $toStatus,
        ?string $note,
        ImprovementSessionEventVisibility $visibility,
        ?array $metadata = null,
    ): void {
        $session->events()->create([
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus?->value,
            'note' => $note,
            'visibility' => $visibility,
            'metadata' => $metadata,
        ]);
    }

    private function completeRelatedNotificationActions(ImprovementSession $session): void
    {
        OfficeNotification::query()
            ->where('type', 'REPEATED_POOR')
            ->where('related_type', ImprovementSession::class)
            ->where('related_id', $session->id)
            ->whereNull('action_completed_at')
            ->update([
                'action_completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function sessionStatus(ImprovementSession $session): ImprovementSessionStatus
    {
        $status = $session->getAttribute('status');

        if ($status instanceof ImprovementSessionStatus) {
            return $status;
        }

        return ImprovementSessionStatus::from((string) $status);
    }

    private function performanceRating(
        ?EmployeeMonthlyQualityPerformance $performance,
    ): ?PerformanceRating {
        if ($performance === null) {
            return null;
        }

        $rating = $performance->getAttribute('rating');

        if ($rating instanceof PerformanceRating) {
            return $rating;
        }

        return PerformanceRating::tryFrom((string) $rating);
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->toDateString();
        }

        return CarbonImmutable::parse((string) $value)->toDateString();
    }

    private function normalizeMonth(CarbonInterface $month): CarbonImmutable
    {
        return CarbonImmutable::instance($month)
            ->setTimezone((string) config('app.timezone', 'UTC'))
            ->startOfMonth();
    }
}
