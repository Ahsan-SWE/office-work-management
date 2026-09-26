<?php

namespace App\Actions\Clients;

use App\Enums\AuditAction;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\ClientSheetUrlHistory;
use App\Models\ClientTierHistory;
use App\Support\Audit\AuditLogger;
use App\Support\Clients\ClientNameNormalizer;
use Illuminate\Support\Facades\DB;

class CreateClientAction
{
    public function __construct(
        private readonly ClientNameNormalizer $normalizer,
        private readonly AuditLogger $audit,
    ) {
    }

    public function handle(array $data, int $actorId): Client
    {
        return DB::transaction(function () use ($data, $actorId) {
            $now = now();

            $client = Client::query()->create([
                'client_code' => null,
                'name' => trim($data['name']),
                'normalized_name' => $this->normalizer->normalize($data['name']),
                'google_sheet_url' => $data['google_sheet_url'],
                'current_tier_id' => $data['current_tier_id'] ?? null,
                'status' => ClientStatus::ACTIVE,
                'start_date' => $data['start_date'] ?? null,
                'expected_end_date' => $data['expected_end_date'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $client->update([
                'client_code' => sprintf('CL-%06d', $client->id),
            ]);

            ClientSheetUrlHistory::query()->create([
                'client_id' => $client->id,
                'url' => $client->google_sheet_url,
                'effective_at' => $now,
                'changed_by' => $actorId,
            ]);

            if ($client->current_tier_id) {
                ClientTierHistory::query()->create([
                    'client_id' => $client->id,
                    'tier_id' => $client->current_tier_id,
                    'effective_at' => $now,
                    'change_reason' => 'INITIAL_TIER',
                    'changed_by' => $actorId,
                ]);
            }

            $this->audit->log(
                AuditAction::CLIENT_CREATED->value,
                $client,
                null,
                null,
                $client->only([
                    'client_code',
                    'name',
                    'google_sheet_url',
                    'current_tier_id',
                    'status',
                    'start_date',
                    'expected_end_date',
                ]),
            );

            return $client->fresh(['currentTier']);
        });
    }
}
