<?php

namespace App\Http\Controllers\TeamLeader;

use App\Http\Controllers\Controller;
use App\Models\QcReview;
use App\Models\Team;
use App\Models\User;
use App\Services\Qc\QcEscalationService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QcEscalationController extends Controller
{
    public function index(Request $request): View
    {
        $reviews = QcReview::query()
            ->with([
                'submission.assignment.workOrder.client:id,client_code,name',
                'responsibleEmployee:id,name,email,primary_team_id',
                'reviewer:id,name,email',
                'escalation',
                'latestOverride',
            ])
            ->whereNotNull('reviewed_at')
            ->whereHas(
                'responsibleEmployee.primaryTeam',
                fn ($query) => $query->where('team_leader_id', $request->user()->id)
            )
            ->latest('reviewed_at')
            ->paginate(30);

        return view('team-leader.qc-escalations.index', compact('reviews'));
    }

    public function show(Request $request, QcReview $review): View
    {
        $this->authorizeTeamReview($request, $review);

        $review->load([
            'submission.assignment.workOrder.client',
            'responsibleEmployee.primaryTeam',
            'reviewer',
            'issues.reason',
            'bonusItems.reason',
            'escalation.raiser',
            'escalation.resolver',
            'overrides.superAdmin',
            'latestOverride',
        ]);

        return view('team-leader.qc-escalations.show', compact('review'));
    }

    public function store(
        Request $request,
        QcReview $review,
        QcEscalationService $service,
    ): RedirectResponse {
        $this->authorizeTeamReview($request, $review);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        try {
            $service->escalate($review, $request->user(), $data['reason']);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['appeal' => $exception->getMessage()]);
        }

        return back()->with('success', 'QC appeal escalated to Super Admin. Original QC history remains unchanged.');
    }

    private function authorizeTeamReview(Request $request, QcReview $review): void
    {
        /** @var User $employee */
        $employee = $review->responsibleEmployee()->firstOrFail();

        /** @var Team|null $team */
        $team = $employee->primaryTeam()->first();

        abort_unless(
            $team?->team_leader_id === $request->user()->id,
            403
        );
    }
}
