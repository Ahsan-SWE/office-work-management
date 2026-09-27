<?php

namespace App\Models;

use App\Enums\QcReviewResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcReviewOverride extends Model
{
    protected $fillable = [
        'qc_review_id',
        'super_admin_id',
        'approved_count',
        'rework_count',
        'result',
        'negative_points',
        'bonus_points',
        'is_major_error',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'approved_count' => 'integer',
            'rework_count' => 'integer',
            'result' => QcReviewResult::class,
            'negative_points' => 'integer',
            'bonus_points' => 'integer',
            'is_major_error' => 'boolean',
        ];
    }

    /** @return BelongsTo<QcReview, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(QcReview::class, 'qc_review_id');
    }

    /** @return BelongsTo<User, $this> */
    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'super_admin_id');
    }
}
