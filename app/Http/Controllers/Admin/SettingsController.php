<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetSection;
use App\Models\CustomJobType;
use App\Models\QcReason;
use App\Models\SocialActivityType;
use App\Models\Tier;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        return view('admin.settings.index', [
            'tiers' => Tier::query()->orderBy('name')->get(),
            'assetSections' => AssetSection::query()->orderBy('sort_order')->orderBy('name')->get(),
            'socialActivities' => SocialActivityType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'customJobTypes' => CustomJobType::query()->orderBy('name')->get(),
            'qcReasons' => QcReason::query()->orderBy('type')->orderBy('name')->get(),
        ]);
    }
}
