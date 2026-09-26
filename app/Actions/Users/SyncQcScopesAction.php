<?php

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Enums\QcScope;
use App\Models\QcUserScope;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class SyncQcScopesAction
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function handle(User $user, array $scopes): void
    {
        $normalized = collect($scopes)
            ->map(fn ($value) => QcScope::from($value)->value)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($user, $normalized) {
            $old = $user->qcScopes()
                ->whereNull('revoked_at')
                ->pluck('scope')
                ->map(fn ($value) => $value instanceof QcScope ? $value->value : (string) $value)
                ->values()
                ->all();

            $current = $user->qcScopes()->whereNull('revoked_at')->get();

            foreach ($current as $row) {
                $scope = $row->scope instanceof QcScope ? $row->scope->value : (string) $row->scope;

                if (! in_array($scope, $normalized, true)) {
                    $row->update(['revoked_at' => now()]);
                }
            }

            foreach ($normalized as $scope) {
                $exists = $user->qcScopes()
                    ->where('scope', $scope)
                    ->whereNull('revoked_at')
                    ->exists();

                if (! $exists) {
                    QcUserScope::query()->create([
                        'user_id' => $user->id,
                        'scope' => $scope,
                        'granted_by' => auth()->id(),
                        'granted_at' => now(),
                    ]);
                }
            }

            $this->audit->log(
                AuditAction::QC_SCOPES_CHANGED->value,
                $user,
                oldValues: ['qc_scopes' => $old],
                newValues: ['qc_scopes' => $normalized],
            );
        });
    }
}
