<?php

return [
    'auth' => [
        'absolute_session_hours' => (int) env('OFFICE_SESSION_HOURS', 24),
    ],

    'qc' => [
        'review_lock_minutes' => (int) env('QC_REVIEW_LOCK_MINUTES', 120),
    ],
];
