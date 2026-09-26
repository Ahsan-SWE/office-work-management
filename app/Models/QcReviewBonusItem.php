<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcReviewBonusItem extends Model
{
    protected $fillable = [
        'qc_review_id',
        'bonus_reason_id',
        'points',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(QcReview::class, 'qc_review_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(QcReason::class, 'bonus_reason_id');
    }
}
