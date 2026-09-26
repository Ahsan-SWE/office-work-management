<?php
namespace App\Models;

use App\Enums\Capability;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCapability extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = ['user_id','capability','created_at'];
    protected function casts(): array { return ['capability'=>Capability::class,'created_at'=>'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
