<?php

namespace App\Http\Controllers\Employee;

use App\Enums\ImprovementSessionEventVisibility;
use App\Http\Controllers\Controller;
use App\Models\ImprovementSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImprovementSessionController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $sessions = ImprovementSession::query()
            ->where('employee_id', $user->id)
            ->latest('performance_month')
            ->paginate(20);

        return view('employee.improvement-sessions.index', [
            'sessions' => $sessions,
        ]);
    }

    public function show(Request $request, ImprovementSession $session): View
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless((int) $session->employee_id === (int) $user->id, 404);

        $session->load([
            'triggerTeam:id,name',
            'events' => fn ($query) => $query
                ->where('visibility', ImprovementSessionEventVisibility::EMPLOYEE->value)
                ->with('actor:id,name'),
        ]);

        return view('employee.improvement-sessions.show', [
            'session' => $session,
        ]);
    }
}
