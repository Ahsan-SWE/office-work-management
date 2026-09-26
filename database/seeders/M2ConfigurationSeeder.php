<?php

namespace Database\Seeders;

use App\Enums\QcReasonType;
use App\Models\AssetSection;
use App\Models\CustomJobType;
use App\Models\QcReason;
use App\Models\SocialActivityType;
use App\Models\Tier;
use Illuminate\Database\Seeder;

class M2ConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Tier 1', 'Tier 2', 'Tier 3', 'Tier 4', 'Tier 5'] as $name) {
            Tier::query()->firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }

        foreach ([
            ['Social Assets', 10],
            ['Web 2.0', 20],
            ['Additional Sites', 30],
            ['WordPress', 40],
        ] as [$name, $sort]) {
            AssetSection::query()->firstOrCreate(
                ['name' => $name],
                ['is_active' => true, 'sort_order' => $sort]
            );
        }

        foreach ([
            ['Follow', true, 10],
            ['Like', true, 20],
            ['Post', true, 30],
            ['Link Share', true, 40],
            ['Comment', true, 50],
            ['Other', false, 60],
        ] as [$name, $full, $sort]) {
            SocialActivityType::query()->firstOrCreate(
                ['name' => $name],
                [
                    'is_active' => true,
                    'is_full_activity_default' => $full,
                    'sort_order' => $sort,
                ]
            );
        }

        foreach ([
            ['New Social Site', 'Create the requested social site/account using the client Google Sheet as the source of truth.'],
            ['Replace Banned Account', 'Replace the banned or unusable account and update the client Google Sheet.'],
            ['Link Share', 'Complete the requested link-share work and record the result in the client Google Sheet.'],
            ['Tier Upgrade', 'Complete the approved tier upgrade scope. Client tier changes only after QC approval.'],
            ['Image Optimization', 'Complete the requested image optimization or suppression work.'],
            ['WordPress Update', 'Complete the requested WordPress update and document the result in the client Google Sheet.'],
        ] as [$name, $instruction]) {
            CustomJobType::query()->firstOrCreate(
                ['name' => $name],
                [
                    'default_instruction' => $instruction,
                    'is_active' => true,
                ]
            );
        }

        foreach ([
            'Wrong/Broken URL',
            'Profile Incomplete',
            'Wrong Information',
            'Missing Account/Site',
            'Wrong Credentials',
            'Instruction Not Followed',
            'Poor Quality',
            'Duplicate/Incorrect Work',
            'WordPress Setup Error',
            'Social Activity Missing',
            'Other',
        ] as $name) {
            QcReason::query()->firstOrCreate(
                ['type' => QcReasonType::NEGATIVE->value, 'name' => $name],
                ['is_active' => true]
            );
        }

        foreach ([
            'New Innovation',
            'Better Work Method',
            'Extra Quality Improvement',
            'Time/Process Improvement',
            'Problem Solved Independently',
            'Other',
        ] as $name) {
            QcReason::query()->firstOrCreate(
                ['type' => QcReasonType::BONUS->value, 'name' => $name],
                ['is_active' => true]
            );
        }
    }
}
