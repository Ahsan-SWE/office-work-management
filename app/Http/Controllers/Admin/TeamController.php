<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Teams\SaveTeamAction;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('admin.teams.index', [
            'teams' => Team::query()
                ->with('teamLeader:id,name,email')
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.teams.form', [
            'team' => new Team(),
            'leaders' => User::role(RoleName::TEAM_LEADER->value)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'status']),
        ]);
    }

    public function store(Request $request, SaveTeamAction $action): RedirectResponse
    {
        $team = $action->handle($this->validated($request));

        return redirect()
            ->route('admin.teams.edit', $team)
            ->with('success', 'Team created successfully.');
    }

    public function edit(Team $team): View
    {
        return view('admin.teams.form', [
            'team' => $team,
            'leaders' => User::role(RoleName::TEAM_LEADER->value)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'status']),
        ]);
    }

    public function update(Request $request, Team $team, SaveTeamAction $action): RedirectResponse
    {
        $action->handle($this->validated($request, $team), $team);

        return back()->with('success', 'Team updated successfully.');
    }

    public function deactivate(Team $team, SaveTeamAction $action): RedirectResponse
    {
        $action->setActive($team, false);

        return back()->with('success', 'Team deactivated.');
    }

    public function reactivate(Team $team, SaveTeamAction $action): RedirectResponse
    {
        $action->setActive($team, true);

        return back()->with('success', 'Team reactivated.');
    }

    private function validated(Request $request, ?Team $team = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('teams', 'name')->ignore($team?->id),
            ],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }
}
