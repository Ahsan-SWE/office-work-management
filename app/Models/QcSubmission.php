<?php

namespace App\Models;

use App\Enums\QcSubmissionStatus;
use App\Enums\QcSubmissionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QcSubmission extends Model
{
    protected $fillable = [
        'submission_code',
        'idempotency_key',
        'assignment_id',
        'submitted_by',
        'submission_type',
        'submitted_count',
        'scope_text',
        'employee_note',
        'source_review_id',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submission_type' => QcSubmissionType::class,
            'status' => QcSubmissionStatus::class,
            'submitted_count' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function sourceReview(): BelongsTo
    {
        return $this->belongsTo(QcReview::class, 'source_review_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(QcReview::class, 'qc_submission_id');
    }
}
