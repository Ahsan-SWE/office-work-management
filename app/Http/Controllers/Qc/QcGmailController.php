<?php

namespace App\Http\Controllers\Qc;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\QcGmailConnection;
use App\Models\QcMajorErrorEmailAttempt;
use App\Support\Audit\AuditLogger;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

class QcGmailController extends Controller
{
    private const SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/gmail.send',
    ];

    public function show(Request $request): View
    {
        abort_unless($request->user()->can('qc.review'), 403);

        $connection = QcGmailConnection::query()
            ->where('user_id', $request->user()->id)
            ->first();

        $attempts = QcMajorErrorEmailAttempt::query()
            ->with('review:id,review_code')
            ->where('sender_user_id', $request->user()->id)
            ->latest('attempted_at')
            ->limit(20)
            ->get();

        return view('qc.gmail.show', compact('connection', 'attempts'));
    }

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('qc.review'), 403);

        return $this->googleProvider()
            ->redirectUrl(route('qc.gmail.callback'))
            ->setScopes(self::SCOPES)
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
                'include_granted_scopes' => 'false',
                'login_hint' => $request->user()->email,
            ])
            ->redirect();
    }

    public function callback(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('qc.review'), 403);

        try {
            $googleUser = $this->googleProvider()
                ->redirectUrl(route('qc.gmail.callback'))
                ->user();

            if (! $googleUser instanceof SocialiteUser) {
                throw new DomainException('Google OAuth returned an unexpected user payload.');
            }

            $googleEmail = mb_strtolower(trim((string) $googleUser->getEmail()));
            $officeEmail = mb_strtolower(trim((string) $request->user()->email));

            if ($googleEmail === '' || $googleEmail !== $officeEmail) {
                throw new DomainException(
                    'QC Gmail must use the same Gmail address as your Office Work Management account.'
                );
            }

            $existing = QcGmailConnection::query()
                ->where('user_id', $request->user()->id)
                ->first();

            $refreshToken = $googleUser->refreshToken ?: $existing?->refresh_token;

            if (! filled($refreshToken)) {
                throw new DomainException(
                    'Google did not return an offline refresh token. Disconnect the app from your Google account and reconnect.'
                );
            }

            $connection = QcGmailConnection::query()->updateOrCreate(
                ['user_id' => $request->user()->id],
                [
                    'google_user_id' => (string) $googleUser->getId(),
                    'email' => $googleEmail,
                    'access_token' => (string) $googleUser->token,
                    'refresh_token' => (string) $refreshToken,
                    'scopes' => self::SCOPES,
                    'expires_at' => filled($googleUser->expiresIn)
                        ? now()->addSeconds(max(60, (int) $googleUser->expiresIn))
                        : null,
                    'connected_at' => now(),
                    'disconnected_at' => null,
                    'last_refreshed_at' => now(),
                    'last_error_at' => null,
                    'last_error_message' => null,
                ],
            );

            $audit->log(
                AuditAction::QC_GMAIL_CONNECTED->value,
                $connection,
                oldValues: null,
                newValues: [
                    'user_id' => $request->user()->id,
                    'email' => $googleEmail,
                    'scopes' => self::SCOPES,
                ],
            );

            return redirect()
                ->route('qc.gmail.show')
                ->with('success', 'QC Gmail connected. Major Error emails can now be sent from your Gmail.');
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('qc.gmail.show')
                ->withErrors([
                    'gmail' => $exception instanceof DomainException
                        ? $exception->getMessage()
                        : 'QC Gmail authorization could not be completed. Please try again.',
                ]);
        }
    }

    private function googleProvider(): GoogleProvider
    {
        $provider = Socialite::driver('google');

        if (! $provider instanceof GoogleProvider) {
            throw new DomainException('Google OAuth provider is unavailable.');
        }

        return $provider;
    }

    public function disconnect(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('qc.review'), 403);

        $connection = QcGmailConnection::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $connection) {
            return back()->with('success', 'QC Gmail is already disconnected.');
        }

        $token = $connection->refresh_token ?: $connection->access_token;

        if (filled($token)) {
            try {
                Http::asForm()
                    ->timeout(10)
                    ->post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $connection->forceFill([
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
            'disconnected_at' => now(),
        ])->save();

        $audit->log(
            AuditAction::QC_GMAIL_DISCONNECTED->value,
            $connection,
            oldValues: ['connected' => true],
            newValues: ['connected' => false, 'user_id' => $request->user()->id],
        );

        return back()->with('success', 'QC Gmail disconnected. Existing send history was preserved.');
    }
}
