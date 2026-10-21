<?php

namespace Saccharine\Bpmn\Listeners;

use Saccharine\Bpmn\Events\BpmnExecutionLogged;
use Symfony\Component\Console\Output\ConsoleOutput;
use Illuminate\Support\Facades\Log;

class BpmnConsoleLogListener
{
    protected ?ConsoleOutput $output = null;

    public function __construct()
    {
        // Only bind ConsoleOutput if running in the CLI environment
        if (app()->runningInConsole()) {
            $this->output = new ConsoleOutput();
        }
    }

    public function handle(BpmnExecutionLogged $event): void
    {
        // 1. Always record structured log to Laravel logger for audit trail
        Log::debug("[BPMN:{$event->type}] {$event->message}", [
            'instance_id' => $event->instanceId,
            'node_id'     => $event->nodeId,
            'context'     => $event->context,
        ]);

        // 2. Format for terminal if running via Artisan (e.g. queue:work)
        if (! $this->output) {
            return;
        }

        $tag = match ($event->type) {
            BpmnExecutionLogged::LEVEL_GATEWAY => '<fg=yellow;options=bold>[BPMN:GATEWAY]</>',
            BpmnExecutionLogged::LEVEL_TASK    => '<fg=cyan;options=bold>[BPMN:TASK]</>',
            BpmnExecutionLogged::LEVEL_WAIT    => '<fg=magenta;options=bold>[BPMN:HIBERNATE]</>',
            BpmnExecutionLogged::LEVEL_SUCCESS => '<fg=green;options=bold>[BPMN:COMPLETE]</>',
            BpmnExecutionLogged::LEVEL_ERROR   => '<fg=red;options=bold>[BPMN:ERROR]</>',
            default                            => '<fg=blue;options=bold>[BPMN:ENGINE]</>',
        };

        $prefix = $event->instanceId ? "<fg=gray>[#{$event->instanceId}]</> " : "";
        $node = $event->nodeId ? "<fg=gray;options=underscore>({$event->nodeId})</> " : "";

        $this->output->writeln("  {$tag} {$prefix}{$node}{$event->message}");
    }
}