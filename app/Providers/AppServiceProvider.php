<?php

namespace App\Providers;

use App\Enums\PermissionDecision;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * SPEC-001 permission precedence:
         *
         *   explicit user DENY / ALLOW
         *        ↓
         *   base Spatie role/direct permission
         *        ↓
         *   normal Laravel Gate / policy handling
         *
         * Spatie's built-in Gate::before registration is disabled in
         * config/permission.php so there is only one permission-check
         * entry point and explicit DENY can truly override a role grant.
         */
        Gate::before(function (User $user, string $ability) {
            $override = $user->permissionOverrides()
                ->where('permission_key', $ability)
                ->first();

            if ($override) {
                return $override->decision === PermissionDecision::ALLOW;
            }

            return $user->checkPermissionTo($ability) ? true : null;
        });
    }
}
