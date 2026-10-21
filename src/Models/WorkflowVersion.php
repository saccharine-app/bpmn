<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowVersion extends Model
{
    protected $guarded = [];
    
    // Inverse relationship to get the name/key
    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(WorkflowNode::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }
}