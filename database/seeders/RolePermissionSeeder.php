<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'admin.manage',
            'users.manage',
            'users.status',
            'user_capabilities.manage',
            'qc_scopes.manage',
            'user_permissions.manage',
            'allowed_emails.manage',
            'teams.manage',
            'team_members.manage',

            'clients.view_all',
            'clients.create',
            'clients.update',
            'clients.deactivate',

            'work.assign',
            'work.edit',
            'work.cancel',
            'work.reassign',
            'work.priority',

            'task.progress',
            'task.submit_qc',
            'task.duplicate_claim',

            'qc.review',
            'qc.reassign',
            'qc.major_error_email',
            'qc.request_review',
            'qc.override_marks',

            'performance.view_all',
            'performance.view_team',
            'performance.view_own',

            'improvement.manage_all',
            'improvement.manage_team',
            'improvement.view_own',

            'reports.review_all',
            'reports.manage_team',
            'reports.export',

            'audit.view_full',
            'settings.manage',
            'tiers.create',
            'comments.create',
            'notifications.view_own',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $superAdmin = Role::findOrCreate(RoleName::SUPER_ADMIN->value, 'web');
        $teamLeader = Role::findOrCreate(RoleName::TEAM_LEADER->value, 'web');
        $employee = Role::findOrCreate(RoleName::EMPLOYEE->value, 'web');
        $qc = Role::findOrCreate(RoleName::QC->value, 'web');

        $superAdmin->syncPermissions(Permission::all());

        $teamLeader->syncPermissions([
            'team_members.manage',
            'clients.view_all',
            'clients.create',
            'clients.update',
            'work.assign',
            'work.edit',
            'work.cancel',
            'work.reassign',
            'work.priority',
            'qc.request_review',
            'performance.view_team',
            'improvement.manage_team',
            'reports.manage_team',
            'reports.export',
            'tiers.create',
            'comments.create',
            'notifications.view_own',
        ]);

        $employee->syncPermissions([
            'task.progress',
            'task.submit_qc',
            'task.duplicate_claim',
            'performance.view_own',
            'improvement.view_own',
            'comments.create',
            'notifications.view_own',
        ]);

        $qc->syncPermissions([
            'qc.review',
            'qc.major_error_email',
            'comments.create',
            'notifications.view_own',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
