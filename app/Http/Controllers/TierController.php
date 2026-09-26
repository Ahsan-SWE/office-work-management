<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\Tier;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TierController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('tiers.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('tiers', 'name')],
        ]);

        $tier = Tier::query()->create([
            'name' => trim($data['name']),
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        $audit->log(
            AuditAction::CATALOG_CREATED->value,
            $tier,
            null,
            null,
            ['catalog' => 'tiers', 'name' => $tier->name]
        );

        return back()->with('success', 'Tier added successfully.');
    }
}
