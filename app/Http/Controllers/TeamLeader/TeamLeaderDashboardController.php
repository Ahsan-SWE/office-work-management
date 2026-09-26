<?php

namespace App\Http\Controllers\TeamLeader;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamLeaderDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $team = Team::query()
            ->where('team_leader_id', $user->id)
            ->first();

        $employees = collect();

        if ($team) {
            $employees = User::role('EMPLOYEE')
                ->where('primary_team_id', $team->id)
                ->with('capabilities')
                ->orderBy('name')
                ->get();
        }

        return view('team-leader.dashboard', [
            'team' => $team,
            'employeeCount' => $employees->count(),
            'activeEmployeeCount' => $employees->where('status', UserStatus::ACTIVE)->count(),
            'inactiveEmployeeCount' => $employees->where('status', UserStatus::INACTIVE)->count(),
            'leftEmployeeCount' => $employees->where('status', UserStatus::LEFT_COMPANY)->count(),
            'capabilityCounts' => [
                'ASSETS' => $employees->filter(fn ($employee) => $employee->capabilities->contains(
                    fn ($row) => $row->capability->value === 'ASSETS'
                ))->count(),
                'SOCIAL' => $employees->filter(fn ($employee) => $employee->capabilities->contains(
                    fn ($row) => $row->capability->value === 'SOCIAL'
                ))->count(),
                'CUSTOM' => $employees->filter(fn ($employee) => $employee->capabilities->contains(
                    fn ($row) => $row->capability->value === 'CUSTOM'
                ))->count(),
            ],
        ]);
    }
}
