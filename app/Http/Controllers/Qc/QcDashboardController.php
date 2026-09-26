<?php

namespace App\Http\Controllers\Qc;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QcDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $scopes = $user->qcScopes()
            ->whereNull('revoked_at')
            ->orderBy('scope')
            ->get();

        return view('qc.dashboard', [
            'qcUser' => $user,
            'scopes' => $scopes,
        ]);
    }
}
