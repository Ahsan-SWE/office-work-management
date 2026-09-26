$ErrorActionPreference = "Stop"

Write-Host "Applying M3 Batch 1 QC review UX patch..." -ForegroundColor Cyan

php artisan optimize:clear
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

npm run build
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`nRunning focused QC review UX test..." -ForegroundColor Cyan
php artisan test tests/Feature/Qc/QcReviewFormUxTest.php
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`nRunning complete automated test suite..." -ForegroundColor Cyan
php artisan test
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "`nM3 Batch 1 QC review UX patch completed successfully." -ForegroundColor Green
Write-Host "Do not commit yet. Verify the review screen in the browser first." -ForegroundColor Yellow
