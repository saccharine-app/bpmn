<?php

namespace Saccharine\Bpmn\Filament\Resources\ProcessDefinitionResource\Pages;

use Saccharine\Bpmn\Filament\Resources\ProcessDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcessDefinitions extends ListRecords
{
    protected static string $resource = ProcessDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}