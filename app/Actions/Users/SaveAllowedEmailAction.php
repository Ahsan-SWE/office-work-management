<?php

namespace App\Actions\Users;

use App\Enums\AllowedEmailStatus;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Enums\TeamStatus;
use App\Models\AllowedEmail;
use App\Models\Team;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveAllowedEmailAction
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function handle(array $data, ?AllowedEmail $allowedEmail = null): AllowedEmail
    {
        return DB::transaction(function () use ($data, $allowedEmail) {
            $allowedEmail ??= new AllowedEmail();

            if ($allowedEmail->exists && $allowedEmail->status === AllowedEmailStatus::REGISTERED) {
                throw ValidationException::withMessages([
                    'email' => 'Registered invitations cannot be edited. Manage the user account instead.',
                ]);
            }

            $role = RoleName::from($data['preselected_role']);

            if ($role === RoleName::SUPER_ADMIN) {
                throw ValidationException::withMessages([
                    'preselected_role' => 'Super Admin cannot be created through the registration allowlist.',
                ]);
            }

            if ($role === RoleName::EMPLOYEE && empty($data['preselected_team_id'])) {
                throw ValidationException::withMessages([
                    'preselected_team_id' => 'Employee invitations require a team.',
                ]);
            }

            if (
                $role === RoleName::EMPLOYEE
                && ! Team::query()
                    ->whereKey($data['preselected_team_id'])
                    ->where('status', TeamStatus::ACTIVE->value)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'preselected_team_id' => 'Employee invitations require an active team.',
                ]);
            }

            if ($role === RoleName::QC && empty($data['preselected_qc_scope'])) {
                throw ValidationException::withMessages([
                    'preselected_qc_scope' => 'QC invitations require an initial QC scope.',
                ]);
            }

            $old = $allowedEmail->exists
                ? $allowedEmail->only(['email', 'preselected_role', 'preselected_team_id', 'preselected_qc_scope', 'status'])
                : null;

            $allowedEmail->fill([
                'email' => Str::lower(trim($data['email'])),
                'preselected_role' => $role,
                'preselected_team_id' => $role === RoleName::EMPLOYEE ? $data['preselected_team_id'] : null,
                'preselected_qc_scope' => $role === RoleName::QC ? $data['preselected_qc_scope'] : null,
                'status' => $allowedEmail->exists ? $allowedEmail->status : AllowedEmailStatus::PENDING,
                'created_by' => $allowedEmail->exists ? $allowedEmail->created_by : auth()->id(),
            ])->save();

            $this->audit->log(
                $old ? AuditAction::ALLOWED_EMAIL_UPDATED->value : AuditAction::ALLOWED_EMAIL_CREATED->value,
                $allowedEmail,
                oldValues: $old,
                newValues: $allowedEmail->fresh()->only([
                    'email', 'preselected_role', 'preselected_team_id', 'preselected_qc_scope', 'status',
                ]),
            );

            return $allowedEmail->fresh();
        });
    }

    public function setEnabled(AllowedEmail $allowedEmail, bool $enabled): AllowedEmail
    {
        if ($allowedEmail->status === AllowedEmailStatus::REGISTERED) {
            throw ValidationException::withMessages([
                'status' => 'Registered invitations are controlled through the user account.',
            ]);
        }

        $old = $allowedEmail->only(['status']);

        $allowedEmail->status = $enabled
            ? AllowedEmailStatus::PENDING
            : AllowedEmailStatus::DISABLED;

        $allowedEmail->save();

        $this->audit->log(
            $enabled ? AuditAction::ALLOWED_EMAIL_ENABLED->value : AuditAction::ALLOWED_EMAIL_DISABLED->value,
            $allowedEmail,
            oldValues: $old,
            newValues: $allowedEmail->fresh()->only(['status']),
        );

        return $allowedEmail->fresh();
    }
}
