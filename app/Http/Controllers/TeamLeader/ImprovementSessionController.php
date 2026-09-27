<?php

namespace App\Http\Controllers\TeamLeader;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImprovementSession\UpdateImprovementSessionRequest;
use App\Models\ImprovementSession;
use App\Models\Team;
use App\Models\User;
use App\Services\Performance\ImprovementSessionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImprovementSessionController extends Controller
{
    public function index(Request $request): View
    {
        $team = $this->teamFor($request);

        $sessions = ImprovementSession::query()
            ->whereHas('employee', fn ($query) => $query->where('primary_team_id', $team->id))
            ->with('employee:id,name,email,primary_team_id')
            ->latest('performance_month')
            ->paginate(20);

        return view('team-leader.improvement-sessions.index', [
            'team' => $team,
            'sessions' => $sessions,
        ]);
    }

    public function show(Request $request, ImprovementSession $session): View
    {
        $this->authorizeCurrentTeam($request, $session);

        $session->load([
            'employee:id,name,email,primary_team_id',
            'triggerTeam:id,name',
            'updatedBy:id,name',
            'events.actor:id,name',
        ]);

        return view('team-leader.improvement-sessions.show', [
            'session' => $session,
        ]);
    }

    public function start(
        Request $request,
        ImprovementSession $session,
        ImprovementSessionService $service,
    ): RedirectResponse {
        $this->authorizeCurrentTeam($request, $session);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $service->start($session, $actor);
        } catch (DomainException $exception) {
            return back()->withErrors(['session' => $exception->getMessage()]);
        }

        return back()->with('success', 'Improvement Session started.');
    }

    public function update(
        UpdateImprovementSessionRequest $request,
        ImprovementSession $session,
        ImprovementSessionService $service,
    ): RedirectResponse {
        $this->authorizeCurrentTeam($request, $session);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $service->updateDetails($session, $request->validated(), $actor);
        } catch (DomainException $exception) {
            return back()->withErrors(['session' => $exception->getMessage()]);
        }

        return back()->with('success', 'Improvement Session updated.');
    }

    public function complete(
        Request $request,
        ImprovementSession $session,
        ImprovementSessionService $service,
    ): RedirectResponse {
        $this->authorizeCurrentTeam($request, $session);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $service->complete($session, $actor);
        } catch (DomainException $exception) {
            return back()->withErrors(['session' => $exception->getMessage()]);
        }

        return back()->with('success', 'Improvement Session completed.');
    }

    private function teamFor(Request $request): Team
    {
        return Team::query()
            ->where('team_leader_id', $request->user()->id)
            ->firstOrFail();
    }

    private function authorizeCurrentTeam(Request $request, ImprovementSession $session): void
    {
        $team = $this->teamFor($request);
        $session->loadMissing('employee:id,primary_team_id');

        abort_unless((int) $session->employee->primary_team_id === (int) $team->id, 404);
    }
}
