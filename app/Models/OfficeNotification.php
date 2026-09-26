<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'related_type',
        'related_id',
        'is_read',
        'read_at',
        'requires_action',
        'action_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'requires_action' => 'boolean',
            'read_at' => 'datetime',
            'action_completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
