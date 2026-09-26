<?php
namespace App\Models;

use App\Enums\PermissionDecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermissionOverride extends Model
{
    use HasFactory;
    protected $fillable = ['user_id','permission_key','decision','changed_by'];
    protected function casts(): array { return ['decision'=>PermissionDecision::class]; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
