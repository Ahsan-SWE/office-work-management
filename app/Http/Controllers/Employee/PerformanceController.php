<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeMonthlyQualityPerformance;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->can('performance.view_own'), 403);

        $month = $this->selectedMonth($request);

        $performance = EmployeeMonthlyQualityPerformance::query()
            ->where('employee_id', $request->user()->id)
            ->whereDate('performance_month', $month->toDateString())
            ->first();

        return view('employee.performance.index', [
            'performance' => $performance,
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
