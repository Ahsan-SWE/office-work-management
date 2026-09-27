<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QcReview;
use App\Models\QcReviewEscalation;
use App\Services\Qc\QcEscalationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QcEscalationController extends Controller
{
    public function index(Request $request): View
    {
        $status = strtoupper((string) $request->query('status', 'OPEN'));

        if (! in_array($status, ['OPEN', 'RESOLVED', 'ALL'], true)) {
            $status = 'OPEN';
        }

        $query = QcReviewEscalation::query()
            ->with([
                'review.submission.assignment.workOrder.client:id,client_code,name',
                'review.responsibleEmployee:id,name,email',
                'review.reviewer:id,name,email',
                'raiser:id,name,email',
                'resolver:id,name,email',
            ])
            ->latest('opened_at');

        if ($status !== 'ALL') {
            $query->where('status', $status);
        }

        return view('admin.qc-escalations.index', [
            'escalations' => $query->paginate(30)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function show(QcReviewEscalation $escalation): View
    {
        $escalation->load([
            'raiser',
            'resolver',
            'review.submission.assignment.workOrder.client',
            'review.responsibleEmployee.primaryTeam.teamLeader',
            'review.reviewer',
            'review.issues.reason',
            'review.bonusItems.reason',
            'review.gmailAttempts',
            'review.overrides.superAdmin',
            'review.latestOverride',
        ]);

        return view('admin.qc-escalations.show', compact('escalation'));
    }

    public function resolve(
        Request $request,
        QcReviewEscalation $escalation,
        QcEscalationService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'resolution_note' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        try {
            $service->resolve($escalation, $request->user(), $data['resolution_note']);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['escalation' => $exception->getMessage()]);
        }

        return back()->with('success', 'QC escalation resolved. No original QC history was changed.');
    }

    public function override(
        Request $request,
        QcReviewEscalation $escalation,
        QcEscalationService $service,
    ): RedirectResponse {
        /** @var QcReview $review */
        $review = $escalation->review()->firstOrFail();

        $data = $request->validate([
            'approved_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'rework_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'negative_points' => ['required', 'integer', 'min:0', 'max:5'],
            'bonus_points' => ['required', 'integer', 'min:0', 'max:5'],
            'is_major_error' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        try {
            $service->override($review, $request->user(), $data);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['override' => $exception->getMessage()]);
        }

        return back()->with('success', 'Super Admin override recorded as a new immutable history entry.');
    }
}
