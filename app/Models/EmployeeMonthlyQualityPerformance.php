<?php

namespace App\Models;

use App\Enums\PerformanceRating;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeMonthlyQualityPerformance extends Model
{
    protected $fillable = [
        'employee_id',
        'performance_month',
        'review_count',
        'negative_points',
        'bonus_points',
        'rating',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'performance_month' => 'immutable_date',
            'review_count' => 'integer',
            'negative_points' => 'integer',
            'bonus_points' => 'integer',
            'rating' => PerformanceRating::class,
            'calculated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
