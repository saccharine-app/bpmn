<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessEdge extends Model
{
    protected $table = 'process_edges';

    protected $guarded = [];

    /**
     * Get the process version that owns this edge.
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProcessVersion::class, 'process_version_id');
    }
}