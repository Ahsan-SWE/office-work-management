<?php

namespace App\Http\Controllers\Qc;

use App\Enums\QcReasonType;
use App\Http\Controllers\Controller;
use App\Models\AssetSection;
use App\Models\QcGmailConnection;
use App\Models\QcReason;
use App\Models\QcSubmission;
use App\Services\Qc\QcReviewService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QcReviewController extends Controller
{
    public function show(Request $request, QcSubmission $submission): View
    {
        $this->authorizeSubmission($request, $submission);

        $submission->load([
            'assignment.employee:id,name,email,primary_team_id',
            'assignment.employee.primaryTeam.teamLeader:id,name,email',
            'assignment.section:id,name',
            'assignment.workOrder.client:id,client_code,name,google_sheet_url',
            'assignment.workOrder.creator:id,name,email',
            'submitter:id,name,email',
            'sourceReview.issues.reason:id,name',
            'review.reviewer:id,name,email',
            'review.releasedBy:id,name,email',
            'review.issues.reason:id,name',
            'review.issues.section:id,name',
            'review.bonusItems.reason:id,name',
            'review.gmailAttempts',
            'review.lockEvents.actor:id,name,email',
            'review.lockEvents.reviewer:id,name,email',
            'review.escalation',
            'review.latestOverride',
            'review.overrides.superAdmin:id,name,email',
        ]);

        $history = QcSubmission::query()
            ->with([
                'review.reviewer:id,name,email',
                'review.issues.reason:id,name',
                'review.bonusItems.reason:id,name',
                'review.latestOverride',
            ])
            ->where('assignment_id', $submission->assignment_id)
            ->where('id', '<=', $submission->id)
            ->orderByDesc('id')
            ->get();

        return view('qc.submissions.show', [
            'submission' => $submission,
            'history' => $history,
            'gmailConnection' => QcGmailConnection::query()
                ->where('user_id', $request->user()->id)
                ->first(),
            'reviewLockMinutes' => max(5, (int) config('office.qc.review_lock_minutes', 120)),
            'negativeReasons' => QcReason::query()
                ->where('type', QcReasonType::NEGATIVE->value)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'bonusReasons' => QcReason::query()
                ->where('type', QcReasonType::BONUS->value)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'sections' => AssetSection::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function start(
        Request $request,
        QcSubmission $submission,
        QcReviewService $service,
    ): RedirectResponse {
        $this->authorizeSubmission($request, $submission);

        try {
            $review = $service->startReview($submission, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['qc' => $exception->getMessage()]);
        }

        return redirect()
            ->route('qc.submissions.show', $submission)
            ->with('success', "{$review->review_code} started. This submission is now locked to you.");
    }

    public function release(
        Request $request,
        QcSubmission $submission,
        QcReviewService $service,
    ): RedirectResponse {
        $this->authorizeSubmission($request, $submission);

        $data = $request->validate([
            'release_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->releaseReview(
                $submission,
                $request->user(),
                $data['release_reason'] ?? null,
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['qc' => $exception->getMessage()]);
        }

        return redirect()
            ->route('qc.queue', ['tab' => 'waiting'])
            ->with('success', 'QC review lock released. The submission is available in the queue again.');
    }

    public function finish(
        Request $request,
        QcSubmission $submission,
        QcReviewService $service,
    ): RedirectResponse {
        $this->authorizeSubmission($request, $submission);

        $data = $request->validate([
            'approved_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'rework_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_major_error' => ['nullable', 'boolean'],
            'review_comment' => ['nullable', 'string', 'max:10000'],

            'issues' => ['nullable', 'array', 'max:50'],
            'issues.*.reason_id' => ['nullable', 'integer', 'exists:qc_reasons,id'],
            'issues.*.section_id' => ['nullable', 'integer', 'exists:asset_sections,id'],
            'issues.*.site_name' => ['nullable', 'string', 'max:255'],
            'issues.*.negative_points' => ['nullable', 'integer', 'min:0', 'max:5'],
            'issues.*.comment' => ['nullable', 'string', 'max:5000'],

            'bonus_items' => ['nullable', 'array', 'max:5'],
            'bonus_items.*.reason_id' => ['nullable', 'integer', 'exists:qc_reasons,id'],
            'bonus_items.*.points' => ['nullable', 'integer', 'min:1', 'max:5'],
            'bonus_items.*.comment' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $review = $service->finishReview($submission, $request->user(), $data);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['qc' => $exception->getMessage()]);
        }

        $message = "{$review->review_code} completed: {$review->result->value}.";

        if ($review->is_major_error) {
            $message .= ' Major Error saved; send the required Gmail escalation from this review page.';
        }

        return redirect()
            ->route('qc.submissions.show', $submission)
            ->with('success', $message);
    }

    private function authorizeSubmission(Request $request, QcSubmission $submission): void
    {
        abort_unless($request->user()->can('qc.review'), 403);

        $submission->loadMissing('assignment.workOrder');

        $scope = $submission->assignment->workOrder->work_type->value;

        $hasScope = $request->user()->qcScopes()
            ->whereNull('revoked_at')
            ->where('scope', $scope)
            ->exists();

        abort_unless($hasScope, 403);
    }
}
