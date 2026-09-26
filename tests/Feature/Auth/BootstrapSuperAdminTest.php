<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('bootstraps only the first super admin', function () {
    $this->artisan('office:bootstrap-super-admin', [
        'email' => 'owner@gmail.com',
        '--name' => 'Office Owner',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'owner@gmail.com')->firstOrFail();

    expect($user->hasRole(RoleName::SUPER_ADMIN->value))->toBeTrue();

    $this->artisan('office:bootstrap-super-admin', [
        'email' => 'second@gmail.com',
    ])->assertFailed();

    expect(User::query()->where('email', 'second@gmail.com')->exists())->toBeFalse();
});
