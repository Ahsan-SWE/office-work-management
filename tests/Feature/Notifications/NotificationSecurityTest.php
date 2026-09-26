<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\OfficeNotification;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('shows only the signed in users notifications', function () {
    $first = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $first->assignRole(RoleName::EMPLOYEE->value);

    $second = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $second->assignRole(RoleName::EMPLOYEE->value);

    OfficeNotification::query()->create([
        'user_id' => $first->id,
        'title' => 'First only',
        'message' => 'Visible to first user.',
    ]);

    OfficeNotification::query()->create([
        'user_id' => $second->id,
        'title' => 'Second only',
        'message' => 'Visible to second user.',
    ]);

    $response = $this
        ->actingAs($first)
        ->withSession([
            'office_session_version' => 1,
            'office_absolute_expires_at' => now()->addHours(24)->timestamp,
        ])
        ->get(route('notifications.index'));

    $response->assertOk()
        ->assertSee('First only')
        ->assertDontSee('Second only');
});

it('prevents a user from marking another users notification as read', function () {
    $first = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $first->assignRole(RoleName::EMPLOYEE->value);

    $second = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $second->assignRole(RoleName::EMPLOYEE->value);

    $notification = OfficeNotification::query()->create([
        'user_id' => $second->id,
        'title' => 'Private notification',
    ]);

    $this->actingAs($first)
        ->withSession([
            'office_session_version' => 1,
            'office_absolute_expires_at' => now()->addHours(24)->timestamp,
        ])
        ->post(route('notifications.read', $notification))
        ->assertNotFound();
});
