<?php

namespace App\Enums;

enum QcSubmissionStatus: string
{
    case WAITING = 'WAITING';
    case REVIEWING = 'REVIEWING';
    case REVIEWED = 'REVIEWED';
}
