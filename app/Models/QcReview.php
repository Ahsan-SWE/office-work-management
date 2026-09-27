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
        'is_major_error',
        'review_comment',
        'started_at',
        'reviewed_at',
        'released_at',
        'released_by',
        'release_reason',
        'major_error_email_sent',
        'major_error_email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_count' => 'integer',
            'rework_count' => 'integer',
            'result' => QcReviewResult::class,
            'bonus_points' => 'integer',
            'negative_points' => 'integer',
            'is_major_error' => 'boolean',
            'started_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'released_at' => 'datetime',
            'major_error_email_sent' => 'boolean',
            'major_error_email_sent_at' => 'datetime',
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

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
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

    public function gmailAttempts(): HasMany
    {
        return $this->hasMany(QcMajorErrorEmailAttempt::class, 'qc_review_id');
    }

    public function escalation(): HasOne
    {
        return $this->hasOne(QcReviewEscalation::class, 'qc_review_id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(QcReviewOverride::class, 'qc_review_id');
    }

    public function latestOverride(): HasOne
    {
        return $this->hasOne(QcReviewOverride::class, 'qc_review_id')->latestOfMany();
    }

    public function lockEvents(): HasMany
    {
        return $this->hasMany(QcReviewLockEvent::class, 'qc_review_id');
    }

    public function effectiveApprovedCount(): int
    {
        return (int) ($this->resolvedOverride()?->approved_count ?? $this->approved_count ?? 0);
    }

    public function effectiveReworkCount(): int
    {
        return (int) ($this->resolvedOverride()?->rework_count ?? $this->rework_count ?? 0);
    }

    public function effectiveNegativePoints(): int
    {
        return (int) ($this->resolvedOverride()?->negative_points ?? $this->negative_points ?? 0);
    }

    public function effectiveBonusPoints(): int
    {
        return (int) ($this->resolvedOverride()?->bonus_points ?? $this->bonus_points ?? 0);
    }

    public function effectiveIsMajorError(): bool
    {
        return (bool) ($this->resolvedOverride()?->is_major_error ?? $this->is_major_error);
    }

    public function effectiveResult(): ?QcReviewResult
    {
        return $this->resolvedOverride()?->result ?? $this->result;
    }

    private function resolvedOverride(): ?QcReviewOverride
    {
        if ($this->relationLoaded('latestOverride')) {
            return $this->getRelation('latestOverride');
        }

        return $this->latestOverride()->first();
    }
}
