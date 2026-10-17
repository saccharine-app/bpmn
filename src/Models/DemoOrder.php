<?php

namespace Saccharine\Bpmn\Models;

use Illuminate\Database\Eloquent\Model;
use Saccharine\Bpmn\Traits\TriggersBpmnWorkflows;

class DemoOrder extends Model
{
    use TriggersBpmnWorkflows;

    protected $table = 'bpmn_demo_orders';

    protected $guarded = [];
}