<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessVersion extends Model
{
    protected $table = 'process_versions';

    protected $guarded = [];
    
    // Inverse relationship to get the name/key
    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProcessDefinition::class, 'process_definition_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(ProcessNode::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(ProcessEdge::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ProcessInstance::class);
    }
}