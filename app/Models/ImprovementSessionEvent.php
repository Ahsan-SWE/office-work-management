<?php

namespace App\Models;

use App\Enums\ImprovementSessionEventType;
use App\Enums\ImprovementSessionEventVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImprovementSessionEvent extends Model
{
    protected $fillable = [
        'improvement_session_id',
        'actor_user_id',
        'event_type',
        'from_status',
        'to_status',
        'note',
        'visibility',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ImprovementSessionEventType::class,
            'visibility' => ImprovementSessionEventVisibility::class,
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<ImprovementSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ImprovementSession::class, 'improvement_session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
