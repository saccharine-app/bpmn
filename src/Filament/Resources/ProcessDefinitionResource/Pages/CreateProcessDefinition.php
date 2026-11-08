<?php

namespace Saccharine\Bpmn\Filament\Resources\ProcessDefinitionResource\Pages;

use Saccharine\Bpmn\Filament\Resources\ProcessDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateProcessDefinition extends CreateRecord
{
    protected static string $resource = ProcessDefinitionResource::class;
}