<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class RolePlaceholderController extends Controller
{
    public function __invoke(Request $request, string $section): View
    {
        $labels = [
            'team-work' => 'Team Work',
            'performance' => 'Performance',
            'improvement-sessions' => 'Improvement Sessions',
            'monthly-reports' => 'Monthly Reports',
            'my-work' => 'My Work',
            'work-history' => 'Work History',
            'my-performance' => 'My Performance',
            'waiting-qc' => 'Waiting for QC',
            'rework-resubmitted' => 'Rework Resubmitted',
            'reviewed-history' => 'Reviewed History',
        ];

        abort_unless(array_key_exists($section, $labels), 404);

        return view('role-placeholder', [
            'label' => $labels[$section],
            'section' => $section,
        ]);
    }
}
