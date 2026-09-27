<?php

namespace App\Enums;

enum PerformanceRating: string
{
    case GOOD = 'GOOD';
    case NEEDS_ATTENTION = 'NEEDS_ATTENTION';
    case POOR = 'POOR';

    public function label(): string
    {
        return match ($this) {
            self::GOOD => 'Good',
            self::NEEDS_ATTENTION => 'Needs Attention',
            self::POOR => 'Poor',
        };
    }
}
