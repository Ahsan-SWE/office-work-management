<?php

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ChangeUserStatusAction
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function handle(User $user, UserStatus $status): User
    {
        if ($user->status === $status) {
            return $user;
        }

        if ($user->hasRole(RoleName::SUPER_ADMIN->value) && $status !== UserStatus::ACTIVE) {
            $activeSuperAdmins = User::role(RoleName::SUPER_ADMIN->value)
                ->where('status', UserStatus::ACTIVE->value)
                ->count();

            if ($activeSuperAdmins <= 1) {
                throw ValidationException::withMessages([
                    'status' => 'The last active Super Admin cannot be deactivated.',
                ]);
            }
        }

        if ($status !== UserStatus::ACTIVE && $user->hasRole(RoleName::TEAM_LEADER->value)) {
            if (Team::query()->where('team_leader_id', $user->id)->where('status', 'ACTIVE')->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Reassign this Team Leader’s active team before deactivating the account.',
                ]);
            }
        }

        if ($status !== UserStatus::ACTIVE && $this->hasBlockingAssignments($user)) {
            throw ValidationException::withMessages([
                'status' => 'This user still has active assignments. Reassign/handover the work first.',
            ]);
        }

        return DB::transaction(function () use ($user, $status) {
            $old = $user->only(['status', 'session_version']);

            $user->status = $status;
            $user->session_version = ((int) $user->session_version) + 1;

            if ($status === UserStatus::LEFT_COMPANY) {
                DB::table('team_memberships')
                    ->where('user_id', $user->id)
                    ->whereNull('ended_at')
                    ->update([
                        'ended_at' => now(),
                        'changed_by' => auth()->id(),
                    ]);

                $user->primary_team_id = null;
            }

            $user->save();

            $this->audit->log(
                AuditAction::USER_STATUS_CHANGED->value,
                $user,
                oldValues: $old,
                newValues: $user->fresh()->only(['status', 'session_version']),
            );

            return $user->fresh();
        });
    }

    private function hasBlockingAssignments(User $user): bool
    {
        if (! Schema::hasTable('assignments')) {
            return false;
        }

        return DB::table('assignments')
            ->where('employee_id', $user->id)
            ->whereIn('status', ['PENDING', 'ONGOING', 'REWORK', 'SUBMITTED_QC', 'QC_REVIEWING'])
            ->exists();
    }
}
