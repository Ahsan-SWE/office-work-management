<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class BootstrapSuperAdmin extends Command
{
    protected $signature = 'office:bootstrap-super-admin
                            {email : Google email address for the first Super Admin}
                            {--name= : Display name}';

    protected $description = 'Create the one-time first Super Admin account before Google sign-in.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please provide a valid email address.');
            return self::FAILURE;
        }

        Role::findOrCreate(RoleName::SUPER_ADMIN->value, 'web');

        if (User::role(RoleName::SUPER_ADMIN->value)->exists()) {
            $this->error('A Super Admin already exists. Use the application admin workflow for additional admins.');
            return self::FAILURE;
        }

        $name = trim((string) $this->option('name'));
        if ($name === '') {
            $name = Str::headline(Str::before($email, '@'));
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        if ($user->exists && $user->roles()->exists()) {
            $this->error('That email already belongs to a registered application user.');
            return self::FAILURE;
        }

        $user->forceFill([
            'name' => $name,
            'password' => null,
            'status' => UserStatus::ACTIVE,
            'session_version' => max(1, (int) ($user->session_version ?: 1)),
            'registered_at' => $user->registered_at ?: now(),
        ])->save();

        $user->syncRoles([RoleName::SUPER_ADMIN->value]);

        $this->info("First Super Admin prepared for {$email}.");
        $this->line('The Google account will be linked on the first successful Google sign-in.');

        return self::SUCCESS;
    }
}
