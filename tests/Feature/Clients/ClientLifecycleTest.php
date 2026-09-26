<?php

use App\Enums\ClientStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Client;
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

function m2LifecycleSession(User $user): array
{
    return [
        'office_session_version' => $user->session_version,
        'office_absolute_expires_at' => now()->addHours(24)->timestamp,
    ];
}

it('deactivates and reactivates a client without deleting history', function () {
    $client = Client::query()->create([
        'client_code' => 'CL-000100',
        'name' => 'Lifecycle Client',
        'normalized_name' => 'lifecycle client',
        'google_sheet_url' => 'https://docs.google.com/spreadsheets/d/lifecycle/edit',
        'status' => ClientStatus::ACTIVE,
        'created_by' => $this->leader->id,
        'updated_by' => $this->leader->id,
    ]);

    $this->actingAs($this->leader)
        ->withSession(m2LifecycleSession($this->leader))
        ->post(route('clients.deactivate', $client))
        ->assertRedirect();

    expect($client->fresh()->status)->toBe(ClientStatus::INACTIVE);

    $this->actingAs($this->leader)
        ->withSession(m2LifecycleSession($this->leader))
        ->post(route('clients.reactivate', $client))
        ->assertRedirect();

    expect($client->fresh()->status)->toBe(ClientStatus::ACTIVE)
        ->and(Client::query()->count())->toBe(1);
});
