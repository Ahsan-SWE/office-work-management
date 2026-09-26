$ErrorActionPreference = "Stop"

Write-Host "Applying M3 Batch 1 current-sheet label regression fix..." -ForegroundColor Cyan

php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`nRunning focused regression test..." -ForegroundColor Cyan
php artisan test tests/Feature/Work/CurrentClientSheetLinkTest.php
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`nRunning complete automated test suite..." -ForegroundColor Cyan
php artisan test
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`nM3 Batch 1 current-sheet label regression fix completed successfully." -ForegroundColor Green
Write-Host "Do not commit yet. Continue browser verification first." -ForegroundColor Yellow
