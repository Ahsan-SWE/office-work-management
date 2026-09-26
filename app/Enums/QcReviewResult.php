<?php

namespace App\Enums;

enum QcReviewResult: string
{
    case APPROVED = 'APPROVED';
    case REWORK_REQUIRED = 'REWORK_REQUIRED';
}
