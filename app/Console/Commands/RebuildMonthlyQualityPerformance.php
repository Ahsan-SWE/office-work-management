<?php

namespace App\Console\Commands;

use App\Services\Performance\MonthlyQualityPerformanceService;
use Illuminate\Console\Command;

class RebuildMonthlyQualityPerformance extends Command
{
    protected $signature = 'performance:rebuild-quality';

    protected $description = 'Rebuild monthly employee quality-performance projections from completed QC reviews.';

    public function handle(MonthlyQualityPerformanceService $service): int
    {
        $count = $service->rebuildAll();

        $this->info("Rebuilt {$count} employee/month quality projection(s).");

        return self::SUCCESS;
    }
}
