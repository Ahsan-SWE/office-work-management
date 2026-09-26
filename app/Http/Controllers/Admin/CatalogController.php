<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\QcReasonType;
use App\Http\Controllers\Controller;
use App\Models\AssetSection;
use App\Models\CustomJobType;
use App\Models\QcReason;
use App\Models\SocialActivityType;
use App\Models\Tier;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    public function store(Request $request, string $catalog, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('settings.manage'), 403);

        [$modelClass, $data] = $this->validatedForCreate($request, $catalog);

        $model = $modelClass::query()->create($data);

        $audit->log(
            AuditAction::CATALOG_CREATED->value,
            $model,
            null,
            null,
            ['catalog' => $catalog] + $model->toArray(),
        );

        return back()->with('success', 'Configuration item added.');
    }

    public function update(
        Request $request,
        string $catalog,
        int $id,
        AuditLogger $audit
    ): RedirectResponse {
        abort_unless($request->user()->can('settings.manage'), 403);

        $modelClass = $this->modelFor($catalog);
        $model = $modelClass::query()->findOrFail($id);

        $old = $model->toArray();
        $data = $this->validatedForUpdate($request, $catalog, $id);

        $model->fill($data)->save();

        $audit->log(
            AuditAction::CATALOG_UPDATED->value,
            $model,
            null,
            ['catalog' => $catalog] + $old,
            ['catalog' => $catalog] + $model->fresh()->toArray(),
        );

        return back()->with('success', 'Configuration item updated.');
    }

    private function validatedForCreate(Request $request, string $catalog): array
    {
        return match ($catalog) {
            'asset-sections' => [
                AssetSection::class,
                $request->validate([
                    'name' => ['required', 'string', 'max:120', Rule::unique('asset_sections', 'name')],
                    'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
                ]) + ['is_active' => true],
            ],
            'social-activities' => [
                SocialActivityType::class,
                $request->validate([
                    'name' => ['required', 'string', 'max:120', Rule::unique('social_activity_types', 'name')],
                    'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
                    'is_full_activity_default' => ['nullable', 'boolean'],
                ]) + ['is_active' => true],
            ],
            'custom-job-types' => [
                CustomJobType::class,
                $request->validate([
                    'name' => ['required', 'string', 'max:160', Rule::unique('custom_job_types', 'name')],
                    'default_instruction' => ['nullable', 'string', 'max:10000'],
                ]) + [
                    'is_active' => true,
                    'created_by' => $request->user()->id,
                ],
            ],
            'qc-reasons' => [
                QcReason::class,
                $request->validate([
                    'type' => ['required', Rule::enum(QcReasonType::class)],
                    'name' => [
                        'required',
                        'string',
                        'max:180',
                        Rule::unique('qc_reasons', 'name')
                            ->where(fn ($q) => $q->where('type', $request->input('type'))),
                    ],
                ]) + ['is_active' => true],
            ],
            default => throw ValidationException::withMessages([
                'catalog' => 'Unknown configuration catalog.',
            ]),
        };
    }

    private function validatedForUpdate(Request $request, string $catalog, int $id): array
    {
        return match ($catalog) {
            'tiers' => $request->validate([
                'name' => ['required', 'string', 'max:120', Rule::unique('tiers', 'name')->ignore($id)],
                'is_active' => ['required', 'boolean'],
            ]),
            'asset-sections' => $request->validate([
                'name' => ['required', 'string', 'max:120', Rule::unique('asset_sections', 'name')->ignore($id)],
                'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
                'is_active' => ['required', 'boolean'],
            ]),
            'social-activities' => $request->validate([
                'name' => ['required', 'string', 'max:120', Rule::unique('social_activity_types', 'name')->ignore($id)],
                'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
                'is_full_activity_default' => ['required', 'boolean'],
                'is_active' => ['required', 'boolean'],
            ]),
            'custom-job-types' => $request->validate([
                'name' => ['required', 'string', 'max:160', Rule::unique('custom_job_types', 'name')->ignore($id)],
                'default_instruction' => ['nullable', 'string', 'max:10000'],
                'is_active' => ['required', 'boolean'],
            ]),
            'qc-reasons' => $request->validate([
                'type' => ['required', Rule::enum(QcReasonType::class)],
                'name' => [
                    'required',
                    'string',
                    'max:180',
                    Rule::unique('qc_reasons', 'name')
                        ->where(fn ($q) => $q->where('type', $request->input('type')))
                        ->ignore($id),
                ],
                'is_active' => ['required', 'boolean'],
            ]),
            default => throw ValidationException::withMessages([
                'catalog' => 'Unknown configuration catalog.',
            ]),
        };
    }

    private function modelFor(string $catalog): string
    {
        return match ($catalog) {
            'tiers' => Tier::class,
            'asset-sections' => AssetSection::class,
            'social-activities' => SocialActivityType::class,
            'custom-job-types' => CustomJobType::class,
            'qc-reasons' => QcReason::class,
            default => abort(404),
        };
    }
}
