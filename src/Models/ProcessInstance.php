<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessInstance extends Model
{
    protected $table = 'process_instances';

    protected $guarded = [];

    protected $casts = [
        'status' => \Saccharine\Bpmn\Enums\ProcessInstanceStatus::class,
    ];

    /**
     * The process version blueprint this instance is following.
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProcessVersion::class, 'process_version_id');
    }
    
    public function tokens(): HasMany
    {
        return $this->hasMany(ProcessToken::class, 'process_instance_id');
    }
}