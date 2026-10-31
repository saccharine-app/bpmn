<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessHistory extends Model
{
    protected $table = 'process_histories';

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