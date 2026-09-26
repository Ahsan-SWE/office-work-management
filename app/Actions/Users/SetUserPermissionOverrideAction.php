<?php

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Enums\PermissionDecision;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class SetUserPermissionOverrideAction
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function set(User $user, string $permissionKey, PermissionDecision $decision): UserPermissionOverride
    {
        Permission::findByName($permissionKey, 'web');

        return DB::transaction(function () use ($user, $permissionKey, $decision) {
            $existing = $user->permissionOverrides()
                ->where('permission_key', $permissionKey)
                ->first();

            $old = $existing?->only(['permission_key', 'decision']);

            $override = UserPermissionOverride::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'permission_key' => $permissionKey,
                ],
                [
                    'decision' => $decision,
                    'changed_by' => auth()->id(),
                ]
            );

            $this->audit->log(
                AuditAction::USER_PERMISSION_OVERRIDE_CHANGED->value,
                $user,
                oldValues: $old,
                newValues: $override->only(['permission_key', 'decision']),
            );

            return $override;
        });
    }

    public function remove(User $user, UserPermissionOverride $override): void
    {
        abort_unless($override->user_id === $user->id, 404);

        DB::transaction(function () use ($user, $override) {
            $old = $override->only(['permission_key', 'decision']);
            $override->delete();

            $this->audit->log(
                AuditAction::USER_PERMISSION_OVERRIDE_REMOVED->value,
                $user,
                oldValues: $old,
                newValues: null,
            );
        });
    }
}
