<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomJobType extends Model
{
    protected $fillable = ['name', 'default_instruction', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
