$ErrorActionPreference = "Stop"

function Set-EnvValue {
    param(
        [string]$Path,
        [string]$Key,
        [string]$Value
    )

    if (-not (Test-Path $Path)) {
        return
    }

    $content = Get-Content $Path -Raw
    $pattern = "(?m)^" + [regex]::Escape($Key) + "=.*$"
    $line = "$Key=$Value"

    if ($content -match $pattern) {
        $content = [regex]::Replace($content, $pattern, $line)
    } else {
        $content = $content.TrimEnd() + "`r`n" + $line + "`r`n"
    }

    Set-Content -Path $Path -Value $content -NoNewline
}

Write-Host "Installing Laravel Socialite..." -ForegroundColor Cyan
composer require laravel/socialite

Set-EnvValue ".env" "SESSION_DRIVER" "database"
Set-EnvValue ".env" "SESSION_LIFETIME" "1440"
Set-EnvValue ".env" "OFFICE_SESSION_HOURS" "24"
Set-EnvValue ".env" "GOOGLE_CLIENT_ID" ""
Set-EnvValue ".env" "GOOGLE_CLIENT_SECRET" ""
Set-EnvValue ".env" "GOOGLE_REDIRECT_URI" "http://localhost:8000/auth/google/callback"

Set-EnvValue ".env.example" "SESSION_DRIVER" "database"
Set-EnvValue ".env.example" "SESSION_LIFETIME" "1440"
Set-EnvValue ".env.example" "OFFICE_SESSION_HOURS" "24"
Set-EnvValue ".env.example" "GOOGLE_CLIENT_ID" ""
Set-EnvValue ".env.example" "GOOGLE_CLIENT_SECRET" ""
Set-EnvValue ".env.example" "GOOGLE_REDIRECT_URI" "http://localhost:8000/auth/google/callback"

Write-Host "Refreshing application and database..." -ForegroundColor Cyan
php artisan optimize:clear
php artisan migrate:fresh --seed

Write-Host "Running automated tests..." -ForegroundColor Cyan
php artisan test

Write-Host ""
Write-Host "M1 Foundation Batch 2 completed." -ForegroundColor Green
Write-Host "Next: configure Google OAuth credentials, then bootstrap the first Super Admin." -ForegroundColor Yellow
