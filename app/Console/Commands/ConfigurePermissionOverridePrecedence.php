<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConfigurePermissionOverridePrecedence extends Command
{
    protected $signature = 'office:configure-permission-overrides';

    protected $description = 'Disable Spatie default Gate registration so Office permission overrides are authoritative.';

    public function handle(): int
    {
        $path = config_path('permission.php');

        if (! is_file($path)) {
            $this->error('config/permission.php was not found.');
            return self::FAILURE;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->error('Could not read config/permission.php.');
            return self::FAILURE;
        }

        $patterns = [
            "/'register_permission_check_method'\s*=>\s*true/",
            '/"register_permission_check_method"\s*=>\s*true/',
        ];

        $replacement = "'register_permission_check_method' => false";
        $updated = preg_replace($patterns, $replacement, $contents, -1, $count);

        if ($updated === null) {
            $this->error('Could not update the permission configuration.');
            return self::FAILURE;
        }

        if ($count === 0) {
            if (str_contains($contents, "'register_permission_check_method' => false")
                || str_contains($contents, '"register_permission_check_method" => false')) {
                $this->info('Spatie default permission Gate registration is already disabled.');
                return self::SUCCESS;
            }

            $this->error('Could not find register_permission_check_method in config/permission.php.');
            return self::FAILURE;
        }

        file_put_contents($path, $updated);

        $this->info('Spatie default permission Gate registration disabled.');
        return self::SUCCESS;
    }
}
