<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialActivityType extends Model
{
    protected $fillable = ['name', 'is_active', 'is_full_activity_default', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_full_activity_default' => 'boolean',
        ];
    }
}
