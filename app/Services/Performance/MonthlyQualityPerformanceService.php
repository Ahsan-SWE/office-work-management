<?php

namespace App\Services\Performance;

use App\Enums\PerformanceRating;
use App\Models\EmployeeMonthlyQualityPerformance;
use App\Models\QcReview;
use App\Models\QcSubmission;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyQualityPerformanceService
{
    public function __construct(
        private readonly ImprovementSessionService $improvementSessions,
    ) {}

    public function recalculateForReview(QcReview $review): void
    {
        if ($review->reviewed_at === null) {
            return;
        }

        $review->loadMissing('submission');

        /** @var QcSubmission|null $submission */
        $submission = $review->submission;
        if ($submission === null || $submission->submitted_at === null) {
            return;
        }

        $this->recalculateEmployeeMonth(
            (int) $review->responsible_employee_id,
            CarbonImmutable::parse((string) $submission->submitted_at),
        );
    }

    public function recalculateEmployeeMonth(int $employeeId, CarbonInterface $month): void
    {
        $monthStart = $this->normalizeMonth($month);
        $nextMonth = $monthStart->addMonth();

        /** @var Collection<int, QcReview> $reviews */
        $reviews = QcReview::query()
            ->where('responsible_employee_id', $employeeId)
            ->whereNotNull('reviewed_at')
            ->whereHas('submission', function ($query) use ($monthStart, $nextMonth): void {
                $query
                    ->where('submitted_at', '>=', $monthStart)
                    ->where('submitted_at', '<', $nextMonth);
            })
            ->with('latestOverride')
            ->get();

        if ($reviews->isEmpty()) {
            EmployeeMonthlyQualityPerformance::query()
                ->where('employee_id', $employeeId)
                ->whereDate('performance_month', $monthStart->toDateString())
                ->delete();

            $this->improvementSessions->reconcileEmployeeMonth($employeeId, $monthStart);

            return;
        }

        $negativePoints = $reviews->sum(
            fn (QcReview $review): int => $review->effectiveNegativePoints()
        );
        $bonusPoints = $reviews->sum(
            fn (QcReview $review): int => $review->effectiveBonusPoints()
        );

        EmployeeMonthlyQualityPerformance::query()->updateOrCreate(
            [
                'employee_id' => $employeeId,
                'performance_month' => $monthStart,
            ],
            [
                'review_count' => $reviews->count(),
                'negative_points' => (int) $negativePoints,
                'bonus_points' => (int) $bonusPoints,
                'rating' => $this->ratingFor((int) $negativePoints),
                'calculated_at' => now(),
            ],
        );

        $this->improvementSessions->reconcileEmployeeMonth($employeeId, $monthStart);
    }

    public function rebuildAll(): int
    {
        /** @var array<string, array{employee_id: int, month: CarbonImmutable}> $pairs */
        $pairs = [];

        QcReview::query()
            ->whereNotNull('reviewed_at')
            ->with('submission:id,submitted_at')
            ->orderBy('id')
            ->chunkById(500, function ($reviews) use (&$pairs): void {
                foreach ($reviews as $review) {
                    /** @var QcSubmission|null $submission */
                    $submission = $review->submission;
                    if ($submission === null || $submission->submitted_at === null) {
                        continue;
                    }

                    $month = $this->normalizeMonth(
                        CarbonImmutable::parse((string) $submission->submitted_at),
                    );
                    $employeeId = (int) $review->responsible_employee_id;
                    $key = $employeeId.'|'.$month->format('Y-m');

                    $pairs[$key] = [
                        'employee_id' => $employeeId,
                        'month' => $month,
                    ];
                }
            });

        DB::transaction(function () use ($pairs): void {
            EmployeeMonthlyQualityPerformance::query()->delete();

            foreach ($pairs as $pair) {
                $this->recalculateEmployeeMonth(
                    $pair['employee_id'],
                    $pair['month'],
                );
            }

            $this->improvementSessions->reconcileExistingSessionsAgainstProjections();
        });

        return count($pairs);
    }

    public function ratingFor(int $negativePoints): PerformanceRating
    {
        $negativePoints = max(0, $negativePoints);

        return match (true) {
            $negativePoints >= 20 => PerformanceRating::POOR,
            $negativePoints >= 10 => PerformanceRating::NEEDS_ATTENTION,
            default => PerformanceRating::GOOD,
        };
    }

    private function normalizeMonth(CarbonInterface $month): CarbonImmutable
    {
        return CarbonImmutable::instance($month)
            ->setTimezone((string) config('app.timezone', 'UTC'))
            ->startOfMonth();
    }
}
