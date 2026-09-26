<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTierHistory extends Model
{
    protected $fillable = [
        'client_id',
        'tier_id',
        'effective_at',
        'ended_at',
        'change_reason',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(Tier::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
