<?php

namespace App\Models;

use App\Enums\QcReviewResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QcReview extends Model
{
    protected $fillable = [
        'review_code',
        'qc_submission_id',
        'reviewer_id',
        'responsible_employee_id',
        'approved_count',
        'rework_count',
        'result',
        'bonus_points',
        'negative_points',
        'review_comment',
        'started_at',
        'reviewed_at',
        'major_error_email_sent',
    ];

    protected function casts(): array
    {
        return [
            'approved_count' => 'integer',
            'rework_count' => 'integer',
            'result' => QcReviewResult::class,
            'bonus_points' => 'integer',
            'negative_points' => 'integer',
            'started_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'major_error_email_sent' => 'boolean',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(QcSubmission::class, 'qc_submission_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function responsibleEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_employee_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(QcReviewIssue::class);
    }

    public function bonusItems(): HasMany
    {
        return $this->hasMany(QcReviewBonusItem::class);
    }

    public function reworkSubmission(): HasOne
    {
        return $this->hasOne(QcSubmission::class, 'source_review_id');
    }
}
