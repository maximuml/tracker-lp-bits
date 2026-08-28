<?php

declare(strict_types=1);

namespace App\Filament\Resources\User;

use App\Filament\Resources\User\TokenResource\Pages\ManageTokens;
use App\Models\PersonalAccessToken;
use App\Support\UserDisplay;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TokenResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'User';

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.token');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('name')->label(__('label.name')),
                TextColumn::make('abilities')
                    ->label(__('token.permission'))
                    ->formatStateUsing(fn ($record): string => $record->abilitiesText),
                TextColumn::make('token')->label(__('token.token')),
                TextColumn::make('tokenable_id')
                    ->label(__('label.username'))
                    ->formatStateUsing(fn ($state) => UserDisplay::adminUsername($state)),
                TextColumn::make('last_used_at')->label(__('token.last_used_at')),
                TextColumn::make('expires_at')->label(__('label.expire_at')),
                TextColumn::make('created_at')->label(__('label.created_at')),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTokens::route('/'),
        ];
    }
}
