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

Write-Host ""
Write-Host "M3 / Batch 1 - Core QC Workflow" -ForegroundColor Green
Write-Host "This script is non-destructive. It does NOT run migrate:fresh." -ForegroundColor Yellow

Run-Step "Clearing Laravel caches..." { php artisan optimize:clear }
Run-Step "Running non-destructive QC migrations..." { php artisan migrate --force }
Run-Step "Refreshing roles and permissions..." { php artisan db:seed --class=RolePermissionSeeder --force }
Run-Step "Refreshing QC reason configuration..." { php artisan db:seed --class=M2ConfigurationSeeder --force }
Run-Step "Building frontend assets..." { npm run build }
Run-Step "Running M3 Batch 1 QC tests..." { php artisan test tests/Feature/Qc }
Run-Step "Running complete automated test suite..." { php artisan test }

Write-Host ""
Write-Host "M3 Batch 1 automated setup completed successfully." -ForegroundColor Green
Write-Host "Next: browser verification. Do NOT start performance/Gmail/reporting work yet." -ForegroundColor Yellow
