<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('allows super admin into the admin dashboard', function () {
    $user = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $user->assignRole(RoleName::SUPER_ADMIN->value);

    $response = $this
        ->actingAs($user)
        ->withSession([
            'office_session_version' => 1,
            'office_absolute_expires_at' => now()->addHours(24)->timestamp,
        ])
        ->get(route('admin.dashboard'));

    $response->assertOk();
});

it('blocks a normal employee from super admin pages', function () {
    $user = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $user->assignRole(RoleName::EMPLOYEE->value);

    $response = $this
        ->actingAs($user)
        ->withSession([
            'office_session_version' => 1,
            'office_absolute_expires_at' => now()->addHours(24)->timestamp,
        ])
        ->get(route('admin.dashboard'));

    $response->assertForbidden();
});
