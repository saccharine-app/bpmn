<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessDefinition extends Model
{
    protected $table = 'process_definitions';

    protected $guarded = [];

    public function versions()
    {
        return $this->hasMany(ProcessVersion::class);
    }
}