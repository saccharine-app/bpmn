<?php

namespace Saccharine\Bpmn\Filament\Resources\ProcessInstanceResource\Pages;

use Saccharine\Bpmn\Filament\Resources\ProcessInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProcessInstances extends ListRecords
{
    protected static string $resource = ProcessInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(), // Instances are created by the engine
        ];
    }
}