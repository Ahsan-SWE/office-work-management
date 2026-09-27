<?php

namespace App\Enums;

enum ImprovementSessionStatus: string
{
    case OPEN = 'OPEN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case CANCELLED_BY_RECALCULATION = 'CANCELLED_BY_RECALCULATION';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED_BY_RECALCULATION => 'Cancelled by recalculation',
        };
    }
}
