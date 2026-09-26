<?php

use App\Enums\QcReasonType;
use App\Models\AssetSection;
use App\Models\CustomJobType;
use App\Models\QcReason;
use App\Models\SocialActivityType;
use App\Models\Tier;
use Database\Seeders\M2ConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the approved milestone two configuration defaults idempotently', function () {
    $this->seed(M2ConfigurationSeeder::class);
    $this->seed(M2ConfigurationSeeder::class);

    expect(Tier::query()->count())->toBe(5)
        ->and(AssetSection::query()->count())->toBe(4)
        ->and(SocialActivityType::query()->count())->toBe(6)
        ->and(CustomJobType::query()->count())->toBeGreaterThanOrEqual(6)
        ->and(QcReason::query()->where('type', QcReasonType::NEGATIVE->value)->count())->toBeGreaterThanOrEqual(10)
        ->and(QcReason::query()->where('type', QcReasonType::BONUS->value)->count())->toBeGreaterThanOrEqual(5);

    expect(
        SocialActivityType::query()
            ->where('is_full_activity_default', true)
            ->count()
    )->toBe(5);
});
