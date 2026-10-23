<?php

namespace Saccharine\Bpmn\Filament\Resources\WorkflowInstanceResource\Pages;

use Saccharine\Bpmn\Filament\Resources\WorkflowInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowInstances extends ListRecords
{
    protected static string $resource = WorkflowInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(), // Instances are created by the engine
        ];
    }
}