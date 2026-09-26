<?php

namespace App\Actions\Auth;

use App\Enums\AllowedEmailStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Exceptions\OfficeAccessDeniedException;
use App\Models\AllowedEmail;
use App\Models\QcUserScope;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class LoginWithGoogleAction
{
    public function handle(SocialiteUser $googleUser): User
    {
        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $googleId = (string) $googleUser->getId();
        $name = trim((string) $googleUser->getName()) ?: $email;

        if ($email === '' || $googleId === '') {
            throw new OfficeAccessDeniedException('Google did not return a valid account identity.');
        }

        return DB::transaction(function () use ($email, $googleId, $name) {
            /** @var User|null $existing */
            $existing = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $this->loginExistingUser($existing, $email, $googleId, $name);
            }

            /** @var AllowedEmail|null $allowed */
            $allowed = AllowedEmail::query()
                ->where('email', $email)
                ->lockForUpdate()
                ->first();

            if (! $allowed || $allowed->status !== AllowedEmailStatus::PENDING) {
                throw new OfficeAccessDeniedException('This Google account has not been approved for registration.');
            }

            if ($allowed->preselected_role === RoleName::EMPLOYEE && ! $allowed->preselected_team_id) {
                throw new OfficeAccessDeniedException('This employee invitation is missing a team assignment.');
            }

            if ($allowed->preselected_role === RoleName::QC && ! $allowed->preselected_qc_scope) {
                throw new OfficeAccessDeniedException('This QC invitation is missing a QC scope.');
            }

            $user = User::query()->create([
                'google_user_id' => $googleId,
                'name' => $name,
                'email' => $email,
                'email_verified_at' => now(),
                'password' => null,
                'primary_team_id' => $allowed->preselected_role === RoleName::EMPLOYEE
                    ? $allowed->preselected_team_id
                    : null,
                'status' => UserStatus::ACTIVE,
                'session_version' => 1,
                'registered_at' => now(),
                'last_login_at' => now(),
            ]);

            $user->assignRole($allowed->preselected_role->value);

            if ($allowed->preselected_role === RoleName::EMPLOYEE) {
                TeamMembership::query()->create([
                    'team_id' => $allowed->preselected_team_id,
                    'user_id' => $user->id,
                    'is_primary' => true,
                    'started_at' => now(),
                    'changed_by' => $allowed->created_by,
                ]);
            }

            if ($allowed->preselected_role === RoleName::QC) {
                QcUserScope::query()->create([
                    'user_id' => $user->id,
                    'scope' => $allowed->preselected_qc_scope,
                    'granted_by' => $allowed->created_by,
                    'granted_at' => now(),
                ]);
            }

            $allowed->update([
                'status' => AllowedEmailStatus::REGISTERED,
                'registered_user_id' => $user->id,
                'registered_at' => now(),
            ]);

            return $user->fresh();
        });
    }

    private function loginExistingUser(User $user, string $email, string $googleId, string $name): User
    {
        if ($user->status !== UserStatus::ACTIVE) {
            throw new OfficeAccessDeniedException('This account is inactive.');
        }

        if ($user->google_user_id && ! hash_equals($user->google_user_id, $googleId)) {
            throw new OfficeAccessDeniedException('This Google account does not match the registered identity.');
        }

        // The very first Super Admin is created only through the CLI bootstrap command.
        // That account does not require an AllowedEmail row.
        if (! $user->hasRole(RoleName::SUPER_ADMIN->value)) {
            $allowed = AllowedEmail::query()
                ->where('email', $email)
                ->where('registered_user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $allowed || $allowed->status !== AllowedEmailStatus::REGISTERED) {
                throw new OfficeAccessDeniedException('This account registration is not valid.');
            }
        }

        $user->forceFill([
            'google_user_id' => $user->google_user_id ?: $googleId,
            'name' => $name,
            'email_verified_at' => $user->email_verified_at ?: now(),
            'last_login_at' => now(),
        ])->save();

        return $user->fresh();
    }
}
