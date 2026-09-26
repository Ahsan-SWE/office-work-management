<?php
namespace App\Models;
use App\Enums\ClientStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Client extends Model {
    protected $fillable=['client_code','name','normalized_name','google_sheet_url','current_tier_id','status','start_date','expected_end_date','created_by','updated_by'];
    protected function casts(): array { return ['status'=>ClientStatus::class,'start_date'=>'date','expected_end_date'=>'date']; }
    public function currentTier(): BelongsTo { return $this->belongsTo(Tier::class,'current_tier_id'); }
    public function sheetUrlHistories(): HasMany { return $this->hasMany(ClientSheetUrlHistory::class)->latest('effective_at'); }
    public function tierHistories(): HasMany { return $this->hasMany(ClientTierHistory::class)->latest('effective_at'); }
    public function workOrders(): HasMany { return $this->hasMany(WorkOrder::class)->latest('created_at'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class,'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class,'updated_by'); }
}
