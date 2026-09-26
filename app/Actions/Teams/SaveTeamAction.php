<?php

namespace App\Actions\Teams;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Enums\TeamStatus;
use App\Enums\UserStatus;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveTeamAction
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function handle(array $data, ?Team $team = null): Team
    {
        return DB::transaction(function () use ($data, $team) {
            $team ??= new Team();

            $old = $team->exists ? $team->only(['name', 'team_leader_id', 'status']) : null;
            $previousLeaderId = $team->team_leader_id;
            $newLeaderId = $data['team_leader_id'] ?? null;

            if ($newLeaderId) {
                $leader = User::query()->findOrFail($newLeaderId);

                if (! $leader->hasRole(RoleName::TEAM_LEADER->value)) {
                    throw ValidationException::withMessages([
                        'team_leader_id' => 'Selected user is not a Team Leader.',
                    ]);
                }

                if ($leader->status !== UserStatus::ACTIVE) {
                    throw ValidationException::withMessages([
                        'team_leader_id' => 'Only an active Team Leader can be assigned to an active team.',
                    ]);
                }

                $alreadyLeadsAnother = Team::query()
                    ->where('team_leader_id', $leader->id)
                    ->where('status', TeamStatus::ACTIVE->value)
                    ->when($team->exists, fn ($q) => $q->whereKeyNot($team->id))
                    ->exists();

                if ($alreadyLeadsAnother) {
                    throw ValidationException::withMessages([
                        'team_leader_id' => 'This Team Leader already leads another active team.',
                    ]);
                }
            }

            $team->fill([
                'name' => $data['name'],
                'team_leader_id' => $newLeaderId,
                'status' => $team->exists ? $team->status : TeamStatus::ACTIVE,
                'created_by' => $team->exists ? $team->created_by : auth()->id(),
            ])->save();

            if ($previousLeaderId && $previousLeaderId !== $newLeaderId) {
                TeamMembership::query()
                    ->where('team_id', $team->id)
                    ->where('user_id', $previousLeaderId)
                    ->whereNull('ended_at')
                    ->update([
                        'ended_at' => now(),
                        'changed_by' => auth()->id(),
                    ]);

                User::query()
                    ->whereKey($previousLeaderId)
                    ->where('primary_team_id', $team->id)
                    ->update(['primary_team_id' => null]);
            }

            if ($newLeaderId && $newLeaderId !== $previousLeaderId) {
                User::query()->whereKey($newLeaderId)->update([
                    'primary_team_id' => $team->id,
                ]);

                TeamMembership::query()->firstOrCreate(
                    [
                        'team_id' => $team->id,
                        'user_id' => $newLeaderId,
                        'ended_at' => null,
                    ],
                    [
                        'is_primary' => true,
                        'started_at' => now(),
                        'changed_by' => auth()->id(),
                    ]
                );
            }

            $this->audit->log(
                $old ? AuditAction::TEAM_UPDATED->value : AuditAction::TEAM_CREATED->value,
                $team,
                oldValues: $old,
                newValues: $team->fresh()->only(['name', 'team_leader_id', 'status']),
            );

            return $team->fresh();
        });
    }

    public function setActive(Team $team, bool $active): Team
    {
        return DB::transaction(function () use ($team, $active) {
            $old = $team->only(['status', 'team_leader_id']);

            if (! $active && $team->team_leader_id) {
                TeamMembership::query()
                    ->where('team_id', $team->id)
                    ->where('user_id', $team->team_leader_id)
                    ->whereNull('ended_at')
                    ->update([
                        'ended_at' => now(),
                        'changed_by' => auth()->id(),
                    ]);

                User::query()
                    ->whereKey($team->team_leader_id)
                    ->where('primary_team_id', $team->id)
                    ->update(['primary_team_id' => null]);

                $team->team_leader_id = null;
            }

            $team->status = $active ? TeamStatus::ACTIVE : TeamStatus::INACTIVE;
            $team->save();

            $this->audit->log(
                $active ? AuditAction::TEAM_REACTIVATED->value : AuditAction::TEAM_DEACTIVATED->value,
                $team,
                oldValues: $old,
                newValues: $team->fresh()->only(['status', 'team_leader_id']),
            );

            return $team->fresh();
        });
    }
}
