<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserPermissionOverride;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('explicit deny overrides a base role permission', function () {
    $leader = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $leader->assignRole(RoleName::TEAM_LEADER->value);

    expect($leader->can('work.assign'))->toBeTrue();

    UserPermissionOverride::query()->create([
        'user_id' => $leader->id,
        'permission_key' => 'work.assign',
        'decision' => 'DENY',
    ]);

    expect($leader->fresh()->can('work.assign'))->toBeFalse();
});

it('explicit allow grants a permission absent from the base role', function () {
    $employee = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $employee->assignRole(RoleName::EMPLOYEE->value);

    expect($employee->can('reports.export'))->toBeFalse();

    UserPermissionOverride::query()->create([
        'user_id' => $employee->id,
        'permission_key' => 'reports.export',
        'decision' => 'ALLOW',
    ]);

    expect($employee->fresh()->can('reports.export'))->toBeTrue();
});
