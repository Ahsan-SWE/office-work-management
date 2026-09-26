<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMembership extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['team_id','user_id','is_primary','started_at','ended_at','changed_by'];

    protected function casts(): array
    {
        return ['is_primary'=>'boolean','started_at'=>'datetime','ended_at'=>'datetime'];
    }

    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
