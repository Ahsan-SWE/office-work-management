<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\AllowedEmail;
use App\Models\Team;
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

it('creates an employee allowlist entry with the selected team', function () {
    $team = Team::query()->create([
        'name' => 'Team Alpha',
        'status' => 'ACTIVE',
        'created_by' => $this->admin->id,
    ]);

    $response = $this
        ->actingAs($this->admin)
        ->withSession(adminSession())
        ->post(route('admin.allowed-emails.store'), [
            'email' => 'NEW.EMPLOYEE@GMAIL.COM',
            'preselected_role' => 'EMPLOYEE',
            'preselected_team_id' => $team->id,
        ]);

    $response->assertRedirect();

    $allowed = AllowedEmail::query()->where('email', 'new.employee@gmail.com')->firstOrFail();

    expect($allowed->preselected_role->value)->toBe('EMPLOYEE')
        ->and($allowed->preselected_team_id)->toBe($team->id);
});

it('requires a team for employee invitations', function () {
    $response = $this
        ->actingAs($this->admin)
        ->withSession(adminSession())
        ->from(route('admin.allowed-emails.create'))
        ->post(route('admin.allowed-emails.store'), [
            'email' => 'employee@gmail.com',
            'preselected_role' => 'EMPLOYEE',
        ]);

    $response->assertRedirect(route('admin.allowed-emails.create'));
    $response->assertSessionHasErrors('preselected_team_id');
});

it('requires an initial scope for qc invitations', function () {
    $response = $this
        ->actingAs($this->admin)
        ->withSession(adminSession())
        ->from(route('admin.allowed-emails.create'))
        ->post(route('admin.allowed-emails.store'), [
            'email' => 'qc@gmail.com',
            'preselected_role' => 'QC',
        ]);

    $response->assertRedirect(route('admin.allowed-emails.create'));
    $response->assertSessionHasErrors('preselected_qc_scope');
});
