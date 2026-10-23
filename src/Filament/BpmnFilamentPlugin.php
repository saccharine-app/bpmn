<?php

namespace Saccharine\Bpmn\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Saccharine\Bpmn\Filament\Resources\WorkflowDefinitionResource;
use Saccharine\Bpmn\Filament\Resources\WorkflowInstanceResource;

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

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
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
