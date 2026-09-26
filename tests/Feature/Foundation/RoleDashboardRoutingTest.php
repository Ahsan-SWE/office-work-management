<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function sessionFor(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('redirects each role to its own dashboard', function (string $role, string $routeName) {
    $user = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $user->assignRole($role);

    $this->actingAs($user)
        ->withSession(sessionFor($user))
        ->get(route('dashboard'))
        ->assertRedirect(route($routeName));
})->with([
    [RoleName::SUPER_ADMIN->value, 'admin.dashboard'],
    [RoleName::TEAM_LEADER->value, 'team-leader.dashboard'],
    [RoleName::EMPLOYEE->value, 'employee.dashboard'],
    [RoleName::QC->value, 'qc.dashboard'],
]);

it('prevents employee from opening team leader pages', function () {
    $employee = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $employee->assignRole(RoleName::EMPLOYEE->value);

    $this->actingAs($employee)
        ->withSession(sessionFor($employee))
        ->get(route('team-leader.dashboard'))
        ->assertForbidden();
});
