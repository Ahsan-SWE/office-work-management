<?php

use App\Enums\ClientStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Client;
use App\Models\Tier;
use App\Models\User;
use Database\Seeders\M2ConfigurationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(M2ConfigurationSeeder::class);

    $this->leader = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $this->leader->assignRole(RoleName::TEAM_LEADER->value);
});

function m2ClientSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('lets a team leader create a client once and stores initial histories', function () {
    $tier = Tier::query()->where('name', 'Tier 1')->firstOrFail();

    $response = $this->actingAs($this->leader)
        ->withSession(m2ClientSession($this->leader))
        ->post(route('clients.store'), [
            'name' => 'Sam Andasi CC Doc',
            'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/abc123/edit',
            'current_tier_id' => $tier->id,
            'start_date' => '2026-09-01',
        ]);

    $client = Client::query()->firstOrFail();

    $response->assertRedirect(route('clients.show', $client));

    expect($client->client_code)->toBe('CL-000001')
        ->and($client->status)->toBe(ClientStatus::ACTIVE);

    $this->assertDatabaseHas('client_sheet_url_histories', [
        'client_id' => $client->id,
        'url' => 'https://docs.google.com/spreadsheets/d/abc123/edit',
    ]);

    $this->assertDatabaseHas('client_tier_histories', [
        'client_id' => $client->id,
        'tier_id' => $tier->id,
        'change_reason' => 'INITIAL_TIER',
    ]);
});

it('warns on a possible duplicate but allows an explicit confirmation', function () {
    $tier = Tier::query()->where('name', 'Tier 1')->firstOrFail();

    Client::query()->create([
        'client_code' => 'CL-000001',
        'name' => 'Sam Andasi CC Doc',
        'normalized_name' => 'sam andasi cc doc',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/original/edit',
        'current_tier_id' => $tier->id,
        'status' => ClientStatus::ACTIVE,
        'created_by' => $this->leader->id,
        'updated_by' => $this->leader->id,
    ]);

    $this->actingAs($this->leader)
        ->withSession(m2ClientSession($this->leader))
        ->from(route('clients.create'))
        ->post(route('clients.store'), [
            'name' => 'Sam Andasi CC Doc',
            'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/another/edit',
            'current_tier_id' => $tier->id,
        ])
        ->assertRedirect(route('clients.create'))
        ->assertSessionHas('duplicate_warning');

    expect(Client::query()->count())->toBe(1);

    $this->actingAs($this->leader)
        ->withSession(m2ClientSession($this->leader))
        ->post(route('clients.store'), [
            'name' => 'Sam Andasi CC Doc',
            'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/another/edit',
            'current_tier_id' => $tier->id,
            'confirm_duplicate' => 1,
        ])
        ->assertRedirect();

    expect(Client::query()->count())->toBe(2);
});

it('preserves old google sheet urls when a client url changes', function () {
    $tier = Tier::query()->where('name', 'Tier 1')->firstOrFail();

    $this->actingAs($this->leader)
        ->withSession(m2ClientSession($this->leader))
        ->post(route('clients.store'), [
            'name' => 'History Client',
            'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/old/edit',
            'current_tier_id' => $tier->id,
        ]);

    $client = Client::query()->firstOrFail();

    $this->actingAs($this->leader)
        ->withSession(m2ClientSession($this->leader))
        ->put(route('clients.update', $client), [
            'name' => 'History Client',
            'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/new/edit',
        ])
        ->assertRedirect(route('clients.show', $client));

    expect($client->fresh()->google_sheet_url)
        ->toBe('https://docs.google.com/spreadsheets/d/new/edit');

    expect($client->sheetUrlHistories()->count())->toBe(2);

    $this->assertDatabaseHas('client_sheet_url_histories', [
        'client_id' => $client->id,
        'url' => 'https://docs.google.com/spreadsheets/d/old/edit',
    ]);
});

it('blocks employee access to the global client directory', function () {
    $employee = User::factory()->create([
        'status' => UserStatus::ACTIVE,
        'session_version' => 1,
    ]);
    $employee->assignRole(RoleName::EMPLOYEE->value);

    $this->actingAs($employee)
        ->withSession(m2ClientSession($employee))
        ->get(route('clients.index'))
        ->assertForbidden();
});
