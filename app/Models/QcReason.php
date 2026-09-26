<?php

namespace App\Models;

use App\Enums\QcReasonType;
use Illuminate\Database\Eloquent\Model;

class QcReason extends Model
{
    protected $fillable = ['type', 'name', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => QcReasonType::class,
            'is_active' => 'boolean',
        ];
    }
}
