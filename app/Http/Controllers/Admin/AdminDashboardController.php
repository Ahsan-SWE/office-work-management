<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AllowedEmailStatus;
use App\Enums\TeamStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AllowedEmail;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'active_teams' => Team::query()->where('status', TeamStatus::ACTIVE->value)->count(),
                'active_users' => User::query()->where('status', UserStatus::ACTIVE->value)->count(),
                'pending_invites' => AllowedEmail::query()->where('status', AllowedEmailStatus::PENDING->value)->count(),
                'qc_users' => User::role('QC')->where('status', UserStatus::ACTIVE->value)->count(),
            ],
            'recentAudit' => AuditLog::query()
                ->with('actor:id,name,email')
                ->latest('created_at')
                ->limit(12)
                ->get(),
        ]);
    }
}
