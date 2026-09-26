<?php
namespace App\Models;

use App\Enums\TeamStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = ['name','team_leader_id','status','created_by'];

    protected function casts(): array { return ['status' => TeamStatus::class]; }

    public function teamLeader(): BelongsTo { return $this->belongsTo(User::class, 'team_leader_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function memberships(): HasMany { return $this->hasMany(TeamMembership::class); }
}
