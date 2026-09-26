$ErrorActionPreference = "Stop"

function Run-Step {
    param(
        [string]$Label,
        [scriptblock]$Command
    )

    Write-Host ""
    Write-Host $Label -ForegroundColor Cyan
    & $Command

    if ($LASTEXITCODE -ne 0) {
        Write-Host ""
        Write-Host "FAILED: $Label" -ForegroundColor Red
        exit $LASTEXITCODE
    }
}

Run-Step "Configuring permission override precedence..." { php artisan office:configure-permission-overrides }
Run-Step "Clearing Laravel caches..." { php artisan optimize:clear }
Run-Step "Running permission override regression tests..." { php artisan test tests/Feature/Admin/PermissionOverrideTest.php }
Run-Step "Running complete automated test suite..." { php artisan test }

Write-Host ""
Write-Host "Batch 3 Hotfix 1 completed successfully." -ForegroundColor Green
