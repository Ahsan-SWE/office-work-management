<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $this->admin->assignRole(RoleName::SUPER_ADMIN->value);
});

it('updates employee capabilities', function () {
    $employee = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $employee->assignRole(RoleName::EMPLOYEE->value);

    $response = $this
        ->actingAs($this->admin)
        ->withSession(adminSession())
        ->put(route('admin.users.capabilities', $employee), [
            'capabilities' => ['ASSETS', 'CUSTOM'],
        ]);

    $response->assertRedirect();

    expect($employee->capabilities()->pluck('capability')->map(fn ($v) => $v->value)->all())
        ->toEqualCanonicalizing(['ASSETS', 'CUSTOM']);
});

it('updates qc scopes with historical revocation', function () {
    $qc = User::factory()->create(['status' => UserStatus::ACTIVE]);
    $qc->assignRole(RoleName::QC->value);

    $this
        ->actingAs($this->admin)
        ->withSession(adminSession())
        ->put(route('admin.users.qc-scopes', $qc), [
            'qc_scopes' => ['ASSETS', 'SOCIAL'],
        ])
        ->assertRedirect();

    expect($qc->qcScopes()->whereNull('revoked_at')->count())->toBe(2);

    $this
        ->actingAs($this->admin)
        ->withSession(adminSession())
        ->put(route('admin.users.qc-scopes', $qc), [
            'qc_scopes' => ['CUSTOM'],
        ])
        ->assertRedirect();

    expect($qc->qcScopes()->whereNull('revoked_at')->pluck('scope')->map(fn ($v) => $v->value)->all())
        ->toEqual(['CUSTOM']);

    expect($qc->qcScopes()->whereNotNull('revoked_at')->count())->toBe(2);
});

it('changes user status and revokes all existing sessions by version', function () {
    $employee = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 4,
    ]);
    $employee->assignRole(RoleName::EMPLOYEE->value);

    $this
        ->actingAs($this->admin)
        ->withSession(adminSession())
        ->put(route('admin.users.status', $employee), [
            'status' => 'INACTIVE',
        ])
        ->assertRedirect();

    $employee->refresh();

    expect($employee->status)->toBe(UserStatus::INACTIVE)
        ->and($employee->session_version)->toBe(5);
});
