<?php

namespace Saccharine\Bpmn\Filament\Resources;

use Filament\Forms;
use Filament\Schemas;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Panel;
use Illuminate\Support\Str;
use Saccharine\Bpmn\Models\ProcessDefinition;
use Saccharine\Bpmn\Filament\Resources\ProcessDefinitionResource\Pages;

class ProcessDefinitionResource extends Resource
{
    protected static ?string $model = ProcessDefinition::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-group';
    protected static \UnitEnum|string|null $navigationGroup = 'Process Orchestration';

    public static function form(Schemas\Schema $schema): Schemas\Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                        if ($operation === 'create') {
                            $set('key', Str::slug($state));
                        }
                    }),

                TextInput::make('key')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText('A unique, machine-readable identifier (e.g., onboarding-process).'),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('key')->searchable(),
                Tables\Columns\TextColumn::make('versions_count')
                    ->counts('versions')
                    ->label('Versions'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                
                Action::make('design')
                    ->label('Open Designer')
                    ->icon('heroicon-o-paint-brush')
                    ->color('info')
                    ->url(fn (ProcessDefinition $record): string => route('bpmn.design', $record->id))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessDefinitions::route('/'),
            'create' => Pages\CreateProcessDefinition::route('/create'),
            'edit' => Pages\EditProcessDefinition::route('/{record}/edit'),
        ];
    }

    public static function getSlug(?Panel $panel = null): string
    {
        $prefix = trim(config('saccharine-bpmn.prefix.web', 'bpmn/processes'), '/');
        
        return $prefix . '/definitions';
    }
}
