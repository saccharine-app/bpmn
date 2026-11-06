<?php

namespace Saccharine\Bpmn\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Panel;
use Workflow\V2\WorkflowStub;
use Saccharine\Bpmn\Models\ProcessInstance;
use Saccharine\Bpmn\Enums\ProcessInstanceStatus;
use Saccharine\Bpmn\Filament\Resources\ProcessInstanceResource\Pages;

class ProcessInstanceResource extends Resource
{
    protected static ?string $model = ProcessInstance::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cpu-chip';
    protected static \UnitEnum|string|null $navigationGroup = 'Process Orchestration';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('version.definition.name')
                    ->label('Process Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (ProcessInstanceStatus $state): string => match ($state) {
                        ProcessInstanceStatus::RUNNING => 'success',
                        ProcessInstanceStatus::SUSPENDED => 'warning',
                        ProcessInstanceStatus::HALTED, ProcessInstanceStatus::FAILED => 'danger',
                        ProcessInstanceStatus::COMPLETED => 'info',
                    }),

                Tables\Columns\TextColumn::make('tokens.bpmn_element_id')
                    ->label('Active Tokens')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('track')
                    ->label('Live Cockpit')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (ProcessInstance $record): string => route('bpmn.instances.track', $record->id))
                    ->openUrlInNewTab(),

                Action::make('suspend')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (ProcessInstance $record) => $record->status === ProcessInstanceStatus::RUNNING)
                    ->action(function (ProcessInstance $record) {
                        $record->update(['status' => ProcessInstanceStatus::SUSPENDED]);
                        WorkflowStub::load($record->durable_workflow_id)->suspendWorkflow();
                    }),

                Action::make('resume')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (ProcessInstance $record) => $record->status === ProcessInstanceStatus::SUSPENDED)
                    ->action(function (ProcessInstance $record) {
                        $record->update(['status' => ProcessInstanceStatus::RUNNING]);
                        WorkflowStub::load($record->durable_workflow_id)->resumeWorkflow();
                    }),

                Action::make('halt')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to permanently halt this process? This cannot be undone.')
                    ->visible(fn (ProcessInstance $record) => in_array($record->status, [ProcessInstanceStatus::RUNNING, ProcessInstanceStatus::SUSPENDED]))
                    ->action(function (ProcessInstance $record) {
                        $record->update(['status' => ProcessInstanceStatus::HALTED]);
                        WorkflowStub::load($record->durable_workflow_id)->haltWorkflow();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessInstances::route('/'),
            'view' => Pages\ViewProcessInstance::route('/{record}'),
        ];
    }

    public static function getSlug(?Panel $panel = null): string
    {
        $prefix = trim(config('saccharine-bpmn.prefix.web', 'bpmn/processes'), '/');
        
        return $prefix . '/instances';
    }
}