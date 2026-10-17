<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowDefinition extends Model
{
    protected $guarded = [];

    public function versions()
    {
        return $this->hasMany(WorkflowVersion::class);
    }
}