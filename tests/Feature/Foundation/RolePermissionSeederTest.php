<?php
use App\Enums\RoleName;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('seeds all four base roles', function () {
    $this->seed(RolePermissionSeeder::class);
    expect(Role::query()->pluck('name')->all())->toContain(
        RoleName::SUPER_ADMIN->value,
        RoleName::TEAM_LEADER->value,
        RoleName::EMPLOYEE->value,
        RoleName::QC->value,
    );
});

it('gives super admin management permissions', function () {
    $this->seed(RolePermissionSeeder::class);
    $role = Role::findByName(RoleName::SUPER_ADMIN->value);
    expect($role->hasPermissionTo('settings.manage'))->toBeTrue()
        ->and($role->hasPermissionTo('qc.override_marks'))->toBeTrue()
        ->and($role->hasPermissionTo('audit.view_full'))->toBeTrue();
});

it('keeps employee permissions restricted', function () {
    $this->seed(RolePermissionSeeder::class);
    $role = Role::findByName(RoleName::EMPLOYEE->value);
    expect($role->hasPermissionTo('task.progress'))->toBeTrue()
        ->and($role->hasPermissionTo('settings.manage'))->toBeFalse()
        ->and($role->hasPermissionTo('audit.view_full'))->toBeFalse();
});
