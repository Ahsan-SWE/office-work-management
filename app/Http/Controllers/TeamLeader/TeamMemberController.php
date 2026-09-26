<?php

namespace App\Http\Controllers\TeamLeader;

use App\Actions\Users\ChangeUserStatusAction;
use App\Actions\Users\SyncEmployeeCapabilitiesAction;
use App\Enums\Capability;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamMemberController extends Controller
{
    public function index(Request $request): View
    {
        $team = $this->teamFor($request);

        $query = User::role(RoleName::EMPLOYEE->value)
            ->where('primary_team_id', $team->id)
            ->with('capabilities')
            ->orderBy('name');

        if ($request->filled('q')) {
            $search = '%'.mb_strtolower($request->string('q')->toString()).'%';

            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
            });
        }

        return view('team-leader.members.index', [
            'team' => $team,
            'members' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $team = $this->teamFor($request);
        $this->assertOwnEmployee($team, $user);

        $user->load('capabilities');

        return view('team-leader.members.show', [
            'team' => $team,
            'member' => $user,
            'statuses' => UserStatus::cases(),
            'capabilities' => Capability::cases(),
        ]);
    }

    public function updateStatus(
        Request $request,
        User $user,
        ChangeUserStatusAction $action
    ): RedirectResponse {
        $team = $this->teamFor($request);
        $this->assertOwnEmployee($team, $user);

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(array_map(fn ($case) => $case->value, UserStatus::cases())),
            ],
        ]);

        $action->handle($user, UserStatus::from($data['status']));

        return back()->with('success', 'Employee status updated.');
    }

    public function updateCapabilities(
        Request $request,
        User $user,
        SyncEmployeeCapabilitiesAction $action
    ): RedirectResponse {
        $team = $this->teamFor($request);
        $this->assertOwnEmployee($team, $user);

        $data = $request->validate([
            'capabilities' => ['array'],
            'capabilities.*' => [
                Rule::in(array_map(fn ($case) => $case->value, Capability::cases())),
            ],
        ]);

        $action->handle($user, $data['capabilities'] ?? []);

        return back()->with('success', 'Employee capabilities updated.');
    }

    private function teamFor(Request $request): Team
    {
        return Team::query()
            ->where('team_leader_id', $request->user()->id)
            ->firstOrFail();
    }

    private function assertOwnEmployee(Team $team, User $user): void
    {
        abort_unless(
            $user->hasRole(RoleName::EMPLOYEE->value)
            && $user->primary_team_id === $team->id,
            404
        );
    }
}
