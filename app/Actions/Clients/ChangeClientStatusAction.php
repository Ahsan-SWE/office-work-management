<?php

namespace App\Actions\Clients;

use App\Enums\AuditAction;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Support\Audit\AuditLogger;

class ChangeClientStatusAction
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function handle(Client $client, ClientStatus $status, int $actorId): Client
    {
        if ($client->status === $status) {
            return $client;
        }

        $old = $client->status->value;

        $client->update([
            'status' => $status,
            'updated_by' => $actorId,
        ]);

        $this->audit->log(
            $status === ClientStatus::ACTIVE
                ? AuditAction::CLIENT_REACTIVATED->value
                : AuditAction::CLIENT_DEACTIVATED->value,
            $client,
            null,
            ['status' => $old],
            ['status' => $status->value],
        );

        return $client;
    }
}
