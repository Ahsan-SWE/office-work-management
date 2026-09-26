<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcReviewIssue extends Model
{
    protected $fillable = [
        'qc_review_id',
        'negative_reason_id',
        'section_id',
        'site_name',
        'negative_points',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'negative_points' => 'integer',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(QcReview::class, 'qc_review_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(QcReason::class, 'negative_reason_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(AssetSection::class, 'section_id');
    }
}
