<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessToken extends Model
{
    protected $table = 'process_tokens';

    protected $guarded = [];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PrcoessInstance::class, 'process_instance_id');
    }
}