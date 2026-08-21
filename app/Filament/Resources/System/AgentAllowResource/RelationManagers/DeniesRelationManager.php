<?php

namespace App\Filament\Resources\System\AgentAllowResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeniesRelationManager extends RelationManager
{
    protected static string $relationship = 'denies';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255)->label(__('label.name')),
                TextInput::make('peer_id')->required()->maxLength(255)->label(__('label.agent_deny.peer_id')),
                TextInput::make('agent')->required()->maxLength(255)->label(__('label.agent_deny.agent')),
                Textarea::make('comment')->label(__('label.comment')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('label.name')),
                TextColumn::make('peer_id')->label(__('label.agent_deny.peer_id')),
                TextColumn::make('agent')->label(__('label.agent_deny.agent')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
