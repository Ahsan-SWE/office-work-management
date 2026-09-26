<?php
namespace App\Models;
use App\Enums\Priority;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class WorkOrder extends Model {
    protected $fillable=['work_code','client_id','work_type','status','priority','sheet_url_snapshot','configuration_snapshot','custom_job_type_id','title','instruction','proof_required','special_custom_split','created_by'];
    protected function casts(): array { return ['work_type'=>WorkType::class,'status'=>WorkOrderStatus::class,'priority'=>Priority::class,'configuration_snapshot'=>'array','proof_required'=>'boolean','special_custom_split'=>'boolean']; }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function customJobType(): BelongsTo { return $this->belongsTo(CustomJobType::class); }
    public function assignments(): HasMany { return $this->hasMany(Assignment::class); }
}
