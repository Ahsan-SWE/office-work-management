<?php

use App\Enums\RoleName;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AllowedEmailController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Middleware\EnsureOfficeSessionIsValid;
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
        if ($request->user()->hasRole(RoleName::SUPER_ADMIN->value)) {
            return redirect()->route('admin.dashboard');
        }

        return view('dashboard', ['user' => $request->user()]);
    })->name('dashboard');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

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
        });
});
