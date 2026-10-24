<?php

namespace Saccharine\Bpmn\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Panel;
use Workflow\WorkflowStub;
use Saccharine\Bpmn\Models\WorkflowInstance;
use Saccharine\Bpmn\Enums\WorkflowInstanceStatus;
use Saccharine\Bpmn\Filament\Resources\WorkflowInstanceResource\Pages;

class WorkflowInstanceResource extends Resource
{
    protected static ?string $model = WorkflowInstance::class;
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
                    ->color(fn (WorkflowInstanceStatus $state): string => match ($state) {
                        WorkflowInstanceStatus::RUNNING => 'success',
                        WorkflowInstanceStatus::SUSPENDED => 'warning',
                        WorkflowInstanceStatus::HALTED, WorkflowInstanceStatus::FAILED => 'danger',
                        WorkflowInstanceStatus::COMPLETED => 'info',
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
                    ->url(fn (WorkflowInstance $record): string => route('bpmn.instances.track', $record->id))
                    ->openUrlInNewTab(),

                Action::make('suspend')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (WorkflowInstance $record) => $record->status === WorkflowInstanceStatus::RUNNING)
                    ->action(function (WorkflowInstance $record) {
                        $record->update(['status' => WorkflowInstanceStatus::SUSPENDED]);
                        WorkflowStub::load($record->durable_workflow_id)->suspendWorkflow();
                    }),

                Action::make('resume')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (WorkflowInstance $record) => $record->status === WorkflowInstanceStatus::SUSPENDED)
                    ->action(function (WorkflowInstance $record) {
                        $record->update(['status' => WorkflowInstanceStatus::RUNNING]);
                        WorkflowStub::load($record->durable_workflow_id)->resumeWorkflow();
                    }),

                Action::make('halt')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to permanently halt this workflow? This cannot be undone.')
                    ->visible(fn (WorkflowInstance $record) => in_array($record->status, [WorkflowInstanceStatus::RUNNING, WorkflowInstanceStatus::SUSPENDED]))
                    ->action(function (WorkflowInstance $record) {
                        $record->update(['status' => WorkflowInstanceStatus::HALTED]);
                        WorkflowStub::load($record->durable_workflow_id)->haltWorkflow();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkflowInstances::route('/'),
            'view' => Pages\ViewWorkflowInstance::route('/{record}'),
        ];
    }

    public static function getSlug(?Panel $panel = null): string
    {
        $prefix = trim(config('saccharine-bpmn.prefix.web', 'bpmn/workflows'), '/');
        
        return $prefix . '/instances';
    }
}