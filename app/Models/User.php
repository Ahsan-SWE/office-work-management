<?php
namespace App\Models;

use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'google_user_id','name','email','password','primary_team_id','status',
        'session_version','registered_at','last_login_at',
    ];

    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'registered_at' => 'datetime',
            'last_login_at' => 'datetime',
            'status' => UserStatus::class,
            'session_version' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function primaryTeam(): BelongsTo { return $this->belongsTo(Team::class, 'primary_team_id'); }
    public function memberships(): HasMany { return $this->hasMany(TeamMembership::class); }
    public function capabilities(): HasMany { return $this->hasMany(UserCapability::class); }
    public function qcScopes(): HasMany { return $this->hasMany(QcUserScope::class); }
    public function permissionOverrides(): HasMany { return $this->hasMany(UserPermissionOverride::class); }
}
