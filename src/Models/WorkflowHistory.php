<?php

namespace Saccharine\BpmnEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowHistory extends Model
{
    public $timestamps = false;
    
    protected $guarded = [];
    
    protected $casts = [
        'state_snapshot' => 'array',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }
}