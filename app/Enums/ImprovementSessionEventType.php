<?php

namespace App\Enums;

enum ImprovementSessionEventType: string
{
    case CREATED_FROM_POOR = 'CREATED_FROM_POOR';
    case STARTED = 'STARTED';
    case PLAN_UPDATED = 'PLAN_UPDATED';
    case NOTE_ADDED = 'NOTE_ADDED';
    case FOLLOW_UP_CHANGED = 'FOLLOW_UP_CHANGED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED_BY_RECALCULATION = 'CANCELLED_BY_RECALCULATION';
    case REOPENED_BY_RECALCULATION = 'REOPENED_BY_RECALCULATION';
    case REPEATED_POOR_ALERTED = 'REPEATED_POOR_ALERTED';

    public function label(): string
    {
        return match ($this) {
            self::CREATED_FROM_POOR => 'Created from Poor month',
            self::STARTED => 'Session started',
            self::PLAN_UPDATED => 'Improvement plan updated',
            self::NOTE_ADDED => 'Notes updated',
            self::FOLLOW_UP_CHANGED => 'Follow-up date changed',
            self::COMPLETED => 'Session completed',
            self::CANCELLED_BY_RECALCULATION => 'Cancelled by recalculation',
            self::REOPENED_BY_RECALCULATION => 'Reopened by recalculation',
            self::REPEATED_POOR_ALERTED => 'Repeated Poor alert sent',
        };
    }
}
