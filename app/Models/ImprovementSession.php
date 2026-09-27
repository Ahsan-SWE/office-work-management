<?php

namespace App\Models;

use App\Enums\ImprovementSessionStatus;
use App\Enums\PerformanceRating;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImprovementSession extends Model
{
    protected $fillable = [
        'employee_id',
        'performance_month',
        'trigger_negative_points',
        'trigger_bonus_points',
        'trigger_rating',
        'trigger_team_id',
        'status',
        'improvement_plan',
        'team_leader_notes',
        'employee_visible_notes',
        'follow_up_date',
        'opened_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'repeated_poor_alerted_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'performance_month' => 'immutable_date',
            'trigger_negative_points' => 'integer',
            'trigger_bonus_points' => 'integer',
            'trigger_rating' => PerformanceRating::class,
            'status' => ImprovementSessionStatus::class,
            'follow_up_date' => 'immutable_date',
            'opened_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'repeated_poor_alerted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function triggerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'trigger_team_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /** @return HasMany<ImprovementSessionEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ImprovementSessionEvent::class)
            ->orderBy('id');
    }
}
