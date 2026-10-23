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

    public function getPluginName(): string
    {
        return 'BPMN Process Orchestration';
    }

    public function getNavigationIcon(): string
    {
        return 'heroicon-o-rectangle-group';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                WorkflowDefinitionResource::class,
                WorkflowInstanceResource::class,
            ])
            ->pages([])
            ->middleware([]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }
}
