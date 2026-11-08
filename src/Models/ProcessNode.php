<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessNode extends Model
{
    protected $table = 'process_nodes';

    protected $guarded = [];
    
    protected $casts = [
        'input_parameters' => 'array',
    ];

    /**
     * Get the process version that owns this node.
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProcessVersion::class, 'process_version_id');
    }
}