<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImprovementSession\UpdateImprovementSessionRequest;
use App\Models\ImprovementSession;
use App\Models\User;
use App\Services\Performance\ImprovementSessionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImprovementSessionController extends Controller
{
    public function index(): View
    {
        $sessions = ImprovementSession::query()
            ->with(['employee:id,name,email,primary_team_id', 'triggerTeam:id,name'])
            ->latest('performance_month')
            ->paginate(25);

        return view('admin.improvement-sessions.index', [
            'sessions' => $sessions,
        ]);
    }

    public function show(ImprovementSession $session): View
    {
        $session->load([
            'employee:id,name,email,primary_team_id',
            'triggerTeam:id,name',
            'updatedBy:id,name',
            'events.actor:id,name',
        ]);

        return view('admin.improvement-sessions.show', [
            'session' => $session,
        ]);
    }

    public function start(
        Request $request,
        ImprovementSession $session,
        ImprovementSessionService $service,
    ): RedirectResponse {
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
        /** @var User $actor */
        $actor = $request->user();

        try {
            $service->complete($session, $actor);
        } catch (DomainException $exception) {
            return back()->withErrors(['session' => $exception->getMessage()]);
        }

        return back()->with('success', 'Improvement Session completed.');
    }
}
