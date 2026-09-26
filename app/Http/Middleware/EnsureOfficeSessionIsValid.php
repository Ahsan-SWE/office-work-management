<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfficeSessionIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $storedVersion = (int) $request->session()->get('office_session_version', 0);
        $expiresAt = (int) $request->session()->get('office_absolute_expires_at', 0);

        $invalid = $user->status !== UserStatus::ACTIVE
            || $storedVersion !== (int) $user->session_version
            || $expiresAt <= 0
            || now()->timestamp >= $expiresAt;

        if ($invalid) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Your session has ended. Please sign in again.');
        }

        return $next($request);
    }
}
