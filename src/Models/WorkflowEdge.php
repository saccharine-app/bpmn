<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowEdge extends Model
{
    protected $guarded = [];

    /**
     * Get the workflow version that owns this edge.
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }
}