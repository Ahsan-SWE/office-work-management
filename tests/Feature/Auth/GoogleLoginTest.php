<?php

use App\Enums\AllowedEmailStatus;
use App\Enums\RoleName;
use App\Enums\TeamStatus;
use App\Enums\UserStatus;
use App\Models\AllowedEmail;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('blocks a google account that is not allowlisted', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-unknown',
        'name' => 'Unknown User',
        'email' => 'unknown@gmail.com',
    ]));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('error');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'unknown@gmail.com']);
});

it('registers a pending allowlisted employee once and assigns the selected team', function () {
    $team = Team::query()->create([
        'name' => 'Team Alpha',
        'status' => TeamStatus::ACTIVE,
    ]);

    AllowedEmail::query()->create([
        'email' => 'employee@gmail.com',
        'preselected_role' => RoleName::EMPLOYEE,
        'preselected_team_id' => $team->id,
        'status' => AllowedEmailStatus::PENDING,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-employee-1',
        'name' => 'Employee One',
        'email' => 'employee@gmail.com',
    ]));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = User::query()->where('email', 'employee@gmail.com')->firstOrFail();

    expect($user->primary_team_id)->toBe($team->id)
        ->and($user->hasRole(RoleName::EMPLOYEE->value))->toBeTrue()
        ->and($user->memberships()->count())->toBe(1)
        ->and($user->session_version)->toBe(1);

    $this->assertDatabaseHas('allowed_emails', [
        'email' => 'employee@gmail.com',
        'registered_user_id' => $user->id,
        'status' => AllowedEmailStatus::REGISTERED->value,
    ]);
});

it('blocks an inactive registered user', function () {
    $user = User::factory()->create([
        'email' => 'inactive@gmail.com',
        'google_user_id' => 'google-inactive',
        'status' => UserStatus::INACTIVE,
        'password' => null,
    ]);
    $user->assignRole(RoleName::EMPLOYEE->value);

    AllowedEmail::query()->create([
        'email' => 'inactive@gmail.com',
        'preselected_role' => RoleName::EMPLOYEE,
        'status' => AllowedEmailStatus::REGISTERED,
        'registered_user_id' => $user->id,
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-inactive',
        'name' => 'Inactive User',
        'email' => 'inactive@gmail.com',
    ]));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
