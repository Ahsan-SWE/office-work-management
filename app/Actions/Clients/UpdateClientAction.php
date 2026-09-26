<?php

namespace App\Actions\Clients;

use App\Enums\AuditAction;
use App\Models\Client;
use App\Models\ClientSheetUrlHistory;
use App\Models\ClientTierHistory;
use App\Support\Audit\AuditLogger;
use App\Support\Clients\ClientNameNormalizer;
use Illuminate\Support\Facades\DB;

class UpdateClientAction
{
    public function __construct(
        private readonly ClientNameNormalizer $normalizer,
        private readonly AuditLogger $audit,
    ) {
    }

    public function handle(
        Client $client,
        array $data,
        int $actorId,
        bool $allowTierCorrection = false,
    ): Client {
        return DB::transaction(function () use ($client, $data, $actorId, $allowTierCorrection) {
            $old = $client->only([
                'name',
                'google_sheet_url',
                'current_tier_id',
                'start_date',
                'expected_end_date',
            ]);

            $now = now();

            if ($client->google_sheet_url !== $data['google_sheet_url']) {
                ClientSheetUrlHistory::query()
                    ->where('client_id', $client->id)
                    ->whereNull('ended_at')
                    ->update([
                        'ended_at' => $now,
                        'updated_at' => $now,
                    ]);

                ClientSheetUrlHistory::query()->create([
                    'client_id' => $client->id,
                    'url' => $data['google_sheet_url'],
                    'effective_at' => $now,
                    'changed_by' => $actorId,
                ]);

                $this->audit->log(
                    AuditAction::CLIENT_SHEET_URL_CHANGED->value,
                    $client,
                    null,
                    ['google_sheet_url' => $client->google_sheet_url],
                    ['google_sheet_url' => $data['google_sheet_url']],
                );
            }

            $newTierId = $allowTierCorrection
                ? ($data['current_tier_id'] ?? null)
                : $client->current_tier_id;

            if ($allowTierCorrection && (int) $client->current_tier_id !== (int) $newTierId) {
                ClientTierHistory::query()
                    ->where('client_id', $client->id)
                    ->whereNull('ended_at')
                    ->update([
                        'ended_at' => $now,
                        'updated_at' => $now,
                    ]);

                if ($newTierId) {
                    ClientTierHistory::query()->create([
                        'client_id' => $client->id,
                        'tier_id' => $newTierId,
                        'effective_at' => $now,
                        'change_reason' => 'SUPER_ADMIN_CORRECTION',
                        'changed_by' => $actorId,
                    ]);
                }

                $this->audit->log(
                    AuditAction::CLIENT_TIER_CORRECTED->value,
                    $client,
                    null,
                    ['current_tier_id' => $client->current_tier_id],
                    ['current_tier_id' => $newTierId],
                );
            }

            $client->update([
                'name' => trim($data['name']),
                'normalized_name' => $this->normalizer->normalize($data['name']),
                'google_sheet_url' => $data['google_sheet_url'],
                'current_tier_id' => $newTierId,
                'start_date' => $data['start_date'] ?? null,
                'expected_end_date' => $data['expected_end_date'] ?? null,
                'updated_by' => $actorId,
            ]);

            $new = $client->fresh()->only([
                'name',
                'google_sheet_url',
                'current_tier_id',
                'start_date',
                'expected_end_date',
            ]);

            if ($old !== $new) {
                $this->audit->log(
                    AuditAction::CLIENT_UPDATED->value,
                    $client,
                    null,
                    $old,
                    $new,
                );
            }

            return $client->fresh(['currentTier']);
        });
    }
}
