<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Saccharine\Bpmn\Traits\TriggersBpmnProcesses;

class DemoOrder extends Model
{
    use TriggersBpmnProcesses;

    protected $table = 'bpmn_demo_orders';

    protected $guarded = [];
}