<?php

namespace App\Actions\Users;

use App\Enums\AuditAction;
use App\Enums\Capability;
use App\Models\User;
use App\Models\UserCapability;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class SyncEmployeeCapabilitiesAction
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function handle(User $user, array $capabilities): void
    {
        $normalized = collect($capabilities)
            ->map(fn ($value) => Capability::from($value)->value)
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($user, $normalized) {
            $old = $user->capabilities()->pluck('capability')->map(
                fn ($value) => $value instanceof Capability ? $value->value : (string) $value
            )->values()->all();

            $user->capabilities()->delete();

            foreach ($normalized as $capability) {
                UserCapability::query()->create([
                    'user_id' => $user->id,
                    'capability' => $capability,
                    'created_at' => now(),
                ]);
            }

            $this->audit->log(
                AuditAction::USER_CAPABILITIES_CHANGED->value,
                $user,
                oldValues: ['capabilities' => $old],
                newValues: ['capabilities' => $normalized],
            );
        });
    }
}
