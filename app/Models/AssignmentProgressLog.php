<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AssignmentProgressLog extends Model {
    public $timestamps=false;
    protected $fillable=['assignment_id','user_id','event_type','from_count','to_count','note','created_at'];
    protected function casts(): array { return ['created_at'=>'datetime']; }
    public function assignment(): BelongsTo { return $this->belongsTo(Assignment::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
