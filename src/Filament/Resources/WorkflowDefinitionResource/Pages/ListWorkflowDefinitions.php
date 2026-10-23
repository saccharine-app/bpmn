<?php

namespace Saccharine\Bpmn\Filament\Resources\WorkflowDefinitionResource\Pages;

use Saccharine\Bpmn\Filament\Resources\WorkflowDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowDefinitions extends ListRecords
{
    protected static string $resource = WorkflowDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}