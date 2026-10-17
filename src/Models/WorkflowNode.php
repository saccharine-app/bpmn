<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowNode extends Model
{
    protected $guarded = [];
    
    protected $casts = [
        'input_parameters' => 'array',
    ];

    /**
     * Get the workflow version that owns this node.
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }
}