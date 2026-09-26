$ErrorActionPreference = "Stop"
composer require "spatie/laravel-permission:^8.0"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan test
Write-Host "M1 Foundation Batch 1 completed." -ForegroundColor Green
