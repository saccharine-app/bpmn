<?php

namespace Saccharine\Bpmn\Filament;

use Filament\Plugin;
use Saccharine\Bpmn\Filament\Resources\WorkflowDefinitionResource;

class BpmnFilamentPlugin implements Plugin
{
    public function getId(): string
    {
        return 'saccharine-bpmn';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerResources();
    }

    protected function registerResources(): void
    {
        \Filament\Facades\Filament::registerResources([
            WorkflowDefinitionResource::class,
            WorkflowInstanceResource::class,
        ]);
    }
}
