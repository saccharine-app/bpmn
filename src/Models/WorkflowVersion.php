<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowVersion extends Model
{
    protected $guarded = [];
    
    // Inverse relationship to get the name/key
    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function nodes()
    {
        return $this->hasMany(WorkflowNode::class);
    }

    public function edges()
    {
        return $this->hasMany(WorkflowEdge::class);
    }

    public function instances()
    {
        return $this->hasMany(WorkflowInstance::class);
    }
}