<?php

declare(strict_types=1);

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\AgentDenyResource\Pages\CreateAgentDeny;
use App\Filament\Resources\System\AgentDenyResource\Pages\EditAgentDeny;
use App\Filament\Resources\System\AgentDenyResource\Pages\ListAgentDenies;
use App\Models\AgentDeny;
use App\Support\Cache;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AgentDenyResource extends Resource
{
    protected static ?string $model = AgentDeny::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.agent_denies');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('family_id')->label('Allow family')
                    ->relationship('family', 'family')->required()->label(__('label.agent_allow.family')),
                TextInput::make('name')->required()->label(__('label.name')),
                TextInput::make('peer_id')->required()->label(__('label.agent_deny.peer_id')),
                TextInput::make('agent')->required()->label(__('label.agent_deny.agent')),
                Textarea::make('comment')->label(__('label.comment')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('family.family')->label(__('label.agent_allow.family')),
                TextColumn::make('name')->searchable()->label(__('label.name')),
                TextColumn::make('peer_id')->searchable()->label(__('label.agent_deny.peer_id')),
                TextColumn::make('agent')->searchable()->label(__('label.agent_deny.agent')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->using(function ($record) {
                    $record->delete();
                    Cache::clearAgentAllowDeny();

                    return redirect(self::getUrl());
                }),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgentDenies::route('/'),
            'create' => CreateAgentDeny::route('/create'),
            'edit' => EditAgentDeny::route('/{record}/edit'),
        ];
    }
}
