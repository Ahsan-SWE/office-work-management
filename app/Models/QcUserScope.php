<?php
namespace App\Models;

use App\Enums\QcScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcUserScope extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['user_id','scope','granted_by','granted_at','revoked_at'];
    protected function casts(): array { return ['scope'=>QcScope::class,'granted_at'=>'datetime','revoked_at'=>'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function grantedBy(): BelongsTo { return $this->belongsTo(User::class, 'granted_by'); }
}
