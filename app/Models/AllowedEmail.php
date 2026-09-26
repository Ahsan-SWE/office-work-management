<?php
namespace App\Models;

use App\Enums\AllowedEmailStatus;
use App\Enums\QcScope;
use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllowedEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'email','preselected_role','preselected_team_id','preselected_qc_scope',
        'status','registered_user_id','created_by','registered_at',
    ];

    protected function casts(): array
    {
        return [
            'preselected_role' => RoleName::class,
            'preselected_qc_scope' => QcScope::class,
            'status' => AllowedEmailStatus::class,
            'registered_at' => 'datetime',
        ];
    }

    public function preselectedTeam(): BelongsTo { return $this->belongsTo(Team::class, 'preselected_team_id'); }
    public function registeredUser(): BelongsTo { return $this->belongsTo(User::class, 'registered_user_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
