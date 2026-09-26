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

it('ends a session when the absolute expiry is reached', function () {
    $user = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $user->assignRole(RoleName::EMPLOYEE->value);

    $response = $this
        ->actingAs($user)
        ->withSession([
            'office_session_version' => 1,
            'office_absolute_expires_at' => now()->subMinute()->timestamp,
        ])
        ->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

it('ends a session when session version is revoked', function () {
    $user = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 2,
    ]);
    $user->assignRole(RoleName::EMPLOYEE->value);

    $response = $this
        ->actingAs($user)
        ->withSession([
            'office_session_version' => 1,
            'office_absolute_expires_at' => now()->addHours(24)->timestamp,
        ])
        ->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
