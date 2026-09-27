<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\EmployeeMonthlyQualityPerformance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->can('performance.view_all'), 403);

        $month = $this->selectedMonth($request);

        $employees = User::role(RoleName::EMPLOYEE->value)
            ->with('primaryTeam:id,name')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $employeeIds = $employees->getCollection()
            ->map(fn (User $user): int => (int) $user->id)
            ->all();

        /** @var Collection<int, EmployeeMonthlyQualityPerformance> $performances */
        $performances = EmployeeMonthlyQualityPerformance::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('performance_month', $month->toDateString())
            ->get()
            ->keyBy('employee_id');

        return view('admin.performance.index', [
            'employees' => $employees,
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
