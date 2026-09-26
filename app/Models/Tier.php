<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tier extends Model
{
    protected $fillable = ['name', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
