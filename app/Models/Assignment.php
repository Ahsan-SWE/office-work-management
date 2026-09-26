<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\Priority;
use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = [
        'assignment_code',
        'work_order_id',
        'employee_id',
        'section_id',
        'scope_type',
        'scope_text',
        'scope_snapshot',
        'assigned_count',
        'completed_count',
        'priority',
        'status',
        'proof_required',
        'instruction',
        'assigned_by',
        'assigned_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_type' => ScopeType::class,
            'priority' => Priority::class,
            'status' => AssignmentStatus::class,
            'scope_snapshot' => 'array',
            'proof_required' => 'boolean',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'assigned_count' => 'integer',
            'completed_count' => 'integer',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(AssetSection::class, 'section_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(AssignmentProgressLog::class)->latest('created_at');
    }

    public function qcSubmissions(): HasMany
    {
        return $this->hasMany(QcSubmission::class);
    }
}
