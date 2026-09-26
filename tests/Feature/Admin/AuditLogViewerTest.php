<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('allows only super admin to view the full audit log', function () {
    $admin = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $admin->assignRole(RoleName::SUPER_ADMIN->value);

    $employee = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $employee->assignRole(RoleName::EMPLOYEE->value);

    AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'TEST_ACTION',
        'entity_type' => User::class,
        'entity_id' => $employee->id,
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->withSession([
            'office_session_version' => 1,
            'office_absolute_expires_at' => now()->addHours(24)->timestamp,
        ])
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertSee('TEST_ACTION');

    $this->actingAs($employee)
        ->withSession([
            'office_session_version' => 1,
            'office_absolute_expires_at' => now()->addHours(24)->timestamp,
        ])
        ->get(route('admin.audit-logs.index'))
        ->assertForbidden();
});
