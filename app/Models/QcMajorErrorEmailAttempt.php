<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcMajorErrorEmailAttempt extends Model
{
    protected $fillable = [
        'qc_review_id',
        'sender_user_id',
        'attempt_no',
        'recipient_email',
        'cc_emails',
        'extra_cc_emails',
        'subject',
        'body',
        'status',
        'gmail_message_id',
        'attempted_at',
        'sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'cc_emails' => 'array',
            'extra_cc_emails' => 'array',
            'attempted_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<QcReview, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(QcReview::class, 'qc_review_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
