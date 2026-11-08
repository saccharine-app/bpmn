<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessHistoryEvent extends Model
{
    protected $table = 'process_history_events';

    public $timestamps = false;
    
    protected $guarded = [];
    
    protected $casts = [
        'state_snapshot' => 'array',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ProcessInstance::class, 'process_instance_id');
    }
}