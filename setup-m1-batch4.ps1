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

Run-Step "Clearing Laravel caches..." { php artisan optimize:clear }
Run-Step "Running non-destructive migrations..." { php artisan migrate --force }
Run-Step "Refreshing roles and permissions..." { php artisan db:seed --class=RolePermissionSeeder --force }
Run-Step "Building frontend assets..." { npm run build }
Run-Step "Running complete automated test suite..." { php artisan test }

Write-Host ""
Write-Host "M1 Foundation Batch 4 completed successfully." -ForegroundColor Green
Write-Host "Milestone 1 foundation is ready for final browser verification." -ForegroundColor Yellow
