<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetSection extends Model
{
    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
