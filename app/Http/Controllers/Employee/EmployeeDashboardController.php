<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load(['primaryTeam:id,name', 'capabilities']);

        return view('employee.dashboard', [
            'employee' => $user,
        ]);
    }
}
