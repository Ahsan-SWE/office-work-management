<?php

use App\Enums\RoleName;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AllowedEmailController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\Employee\EmployeeDashboardController;
use App\Http\Controllers\Employee\EmployeeWorkController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Qc\QcDashboardController;
use App\Http\Controllers\RolePlaceholderController;
use App\Http\Controllers\TeamLeader\TeamLeaderDashboardController;
use App\Http\Controllers\TeamLeader\TeamMemberController;
use App\Http\Controllers\TierController;
use App\Http\Controllers\WorkController;
use App\Http\Middleware\EnsureOfficeSessionIsValid;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');

    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->name('auth.google.redirect');

    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->name('auth.google.callback');
});

Route::middleware(['auth', EnsureOfficeSessionIsValid::class])->group(function () {
    Route::get('/dashboard', function (Request $request) {
        return match (true) {
            $request->user()->hasRole(RoleName::SUPER_ADMIN->value) => redirect()->route('admin.dashboard'),
            $request->user()->hasRole(RoleName::TEAM_LEADER->value) => redirect()->route('team-leader.dashboard'),
            $request->user()->hasRole(RoleName::EMPLOYEE->value) => redirect()->route('employee.dashboard'),
            $request->user()->hasRole(RoleName::QC->value) => redirect()->route('qc.dashboard'),
            default => abort(403),
        };
    })->name('dashboard');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');

    Route::middleware(
        EnsureRole::class.':'.RoleName::SUPER_ADMIN->value.','.RoleName::TEAM_LEADER->value
    )->group(function () {
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
        Route::post('/clients/{client}/deactivate', [ClientController::class, 'deactivate'])->name('clients.deactivate');
        Route::post('/clients/{client}/reactivate', [ClientController::class, 'reactivate'])->name('clients.reactivate');

        Route::post('/tiers', [TierController::class, 'store'])->name('tiers.store');

        Route::get('/work', [WorkController::class, 'index'])->name('work.index');
        Route::get('/work/create', [WorkController::class, 'create'])->name('work.create');
        Route::post('/work', [WorkController::class, 'store'])->name('work.store');
        Route::get('/work/{workOrder}', [WorkController::class, 'show'])->name('work.show');
    });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(EnsureSuperAdmin::class)
        ->group(function () {
            Route::get('/', AdminDashboardController::class)->name('dashboard');

            Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
            Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
            Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
            Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
            Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
            Route::post('/teams/{team}/deactivate', [TeamController::class, 'deactivate'])->name('teams.deactivate');
            Route::post('/teams/{team}/reactivate', [TeamController::class, 'reactivate'])->name('teams.reactivate');

            Route::get('/allowed-emails', [AllowedEmailController::class, 'index'])->name('allowed-emails.index');
            Route::get('/allowed-emails/create', [AllowedEmailController::class, 'create'])->name('allowed-emails.create');
            Route::post('/allowed-emails', [AllowedEmailController::class, 'store'])->name('allowed-emails.store');
            Route::get('/allowed-emails/{allowedEmail}/edit', [AllowedEmailController::class, 'edit'])->name('allowed-emails.edit');
            Route::put('/allowed-emails/{allowedEmail}', [AllowedEmailController::class, 'update'])->name('allowed-emails.update');
            Route::post('/allowed-emails/{allowedEmail}/disable', [AllowedEmailController::class, 'disable'])->name('allowed-emails.disable');
            Route::post('/allowed-emails/{allowedEmail}/enable', [AllowedEmailController::class, 'enable'])->name('allowed-emails.enable');

            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::put('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
            Route::put('/users/{user}/capabilities', [UserController::class, 'updateCapabilities'])->name('users.capabilities');
            Route::put('/users/{user}/qc-scopes', [UserController::class, 'updateQcScopes'])->name('users.qc-scopes');
            Route::post('/users/{user}/permission-overrides', [UserController::class, 'setPermissionOverride'])->name('users.permission-overrides.store');
            Route::delete('/users/{user}/permission-overrides/{override}', [UserController::class, 'removePermissionOverride'])->name('users.permission-overrides.destroy');

            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

            Route::get('/settings', SettingsController::class)->name('settings.index');
            Route::post('/settings/{catalog}', [CatalogController::class, 'store'])->name('settings.catalogs.store');
            Route::put('/settings/{catalog}/{id}', [CatalogController::class, 'update'])->name('settings.catalogs.update');
        });

    Route::prefix('team-leader')
        ->name('team-leader.')
        ->middleware(EnsureRole::class.':'.RoleName::TEAM_LEADER->value)
        ->group(function () {
            Route::get('/', TeamLeaderDashboardController::class)->name('dashboard');
            Route::get('/team', [TeamMemberController::class, 'index'])->name('members.index');
            Route::get('/team/{user}', [TeamMemberController::class, 'show'])->name('members.show');
            Route::put('/team/{user}/status', [TeamMemberController::class, 'updateStatus'])->name('members.status');
            Route::put('/team/{user}/capabilities', [TeamMemberController::class, 'updateCapabilities'])->name('members.capabilities');
            Route::get('/section/{section}', RolePlaceholderController::class)->name('placeholder');
        });

    Route::prefix('employee')
        ->name('employee.')
        ->middleware(EnsureRole::class.':'.RoleName::EMPLOYEE->value)
        ->group(function () {
            Route::get('/', EmployeeDashboardController::class)->name('dashboard');
            Route::get('/work', [EmployeeWorkController::class, 'index'])->name('work.index');
            Route::get('/work/{assignment}', [EmployeeWorkController::class, 'show'])->name('work.show');
            Route::post('/work/{assignment}/start', [EmployeeWorkController::class, 'start'])->name('work.start');
            Route::post('/work/{assignment}/progress', [EmployeeWorkController::class, 'progress'])->name('work.progress');
            Route::get('/section/{section}', RolePlaceholderController::class)->name('placeholder');
        });

    Route::prefix('qc')
        ->name('qc.')
        ->middleware(EnsureRole::class.':'.RoleName::QC->value)
        ->group(function () {
            Route::get('/', QcDashboardController::class)->name('dashboard');
            Route::get('/section/{section}', RolePlaceholderController::class)->name('placeholder');
        });
});
