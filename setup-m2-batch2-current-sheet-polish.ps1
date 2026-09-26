$ErrorActionPreference = "Stop"

Write-Host "Clearing Laravel caches..." -ForegroundColor Cyan
php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "Building frontend assets..." -ForegroundColor Cyan
npm run build
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "Running focused regression test..." -ForegroundColor Cyan
php artisan test tests/Feature/Work/CurrentClientSheetLinkTest.php
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "Running complete automated test suite..." -ForegroundColor Cyan
php artisan test
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host ""
Write-Host "M2 Batch 2 current-sheet polish completed successfully." -ForegroundColor Green
Write-Host "Next: browser verification, then commit. Milestone 3 is still NOT started." -ForegroundColor Yellow
