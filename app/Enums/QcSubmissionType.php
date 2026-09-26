<?php

namespace App\Enums;

enum QcSubmissionType: string
{
    case PARTIAL = 'PARTIAL';
    case FINAL = 'FINAL';
    case REWORK = 'REWORK';
}
