<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\M2ConfigurationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(M2ConfigurationSeeder::class);
});

function m2SettingsSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('allows super admin to manage settings but not team leaders', function () {
    $admin = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $admin->assignRole(RoleName::SUPER_ADMIN->value);

    $leader = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $leader->assignRole(RoleName::TEAM_LEADER->value);

    $this->actingAs($admin)
        ->withSession(m2SettingsSession($admin))
        ->get(route('admin.settings.index'))
        ->assertOk();

    $this->actingAs($leader)
        ->withSession(m2SettingsSession($leader))
        ->get(route('admin.settings.index'))
        ->assertForbidden();
});

it('allows team leaders to add a global tier', function () {
    $leader = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $leader->assignRole(RoleName::TEAM_LEADER->value);

    $this->actingAs($leader)
        ->withSession(m2SettingsSession($leader))
        ->post(route('tiers.store'), [
            'name' => 'VIP Tier',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tiers', [
        'name' => 'VIP Tier',
        'is_active' => true,
    ]);
});
