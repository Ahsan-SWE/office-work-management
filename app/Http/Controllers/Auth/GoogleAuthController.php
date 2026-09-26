<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginWithGoogleAction;
use App\Exceptions\OfficeAccessDeniedException;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request, LoginWithGoogleAction $login): RedirectResponse
    {
        try {
            // Keep Socialite state verification enabled. This is a browser/session flow.
            $googleUser = Socialite::driver('google')->user();
            $user = $login->handle($googleUser);
        } catch (OfficeAccessDeniedException $e) {
            return redirect()
                ->route('login')
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('login')
                ->with('error', 'Google sign-in could not be completed. Please try again.');
        }

        Auth::login($user, false);
        $request->session()->regenerate();

        $request->session()->put([
            'office_session_version' => $user->session_version,
            'office_absolute_expires_at' => now()
                ->addHours((int) config('office.auth.absolute_session_hours', 24))
                ->timestamp,
        ]);

        return redirect()->route('dashboard');
    }
}
