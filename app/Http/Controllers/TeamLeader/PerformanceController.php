<?php

namespace App\Http\Controllers\TeamLeader;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\EmployeeMonthlyQualityPerformance;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->can('performance.view_team'), 403);

        $month = $this->selectedMonth($request);
        $team = Team::query()
            ->where('team_leader_id', $request->user()->id)
            ->firstOrFail();

        $members = User::role(RoleName::EMPLOYEE->value)
            ->where('primary_team_id', $team->id)
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $memberIds = $members->getCollection()
            ->map(fn (User $user): int => (int) $user->id)
            ->all();

        /** @var Collection<int, EmployeeMonthlyQualityPerformance> $performances */
        $performances = EmployeeMonthlyQualityPerformance::query()
            ->whereIn('employee_id', $memberIds)
            ->whereDate('performance_month', $month->toDateString())
            ->get()
            ->keyBy('employee_id');

        return view('team-leader.performance.index', [
            'team' => $team,
            'members' => $members,
            'performances' => $performances,
            'selectedMonth' => $month,
        ]);
    }

    private function selectedMonth(Request $request): CarbonImmutable
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $timezone = (string) config('app.timezone', 'UTC');
        if (filled($data['month'] ?? null)) {
            return CarbonImmutable::createFromFormat(
                'Y-m',
                (string) $data['month'],
                $timezone,
            )->startOfMonth();
        }

        return CarbonImmutable::now($timezone)->startOfMonth();
    }
}
