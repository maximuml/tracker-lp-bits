<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\UsernameChangeLogResource\Pages\ManageUsernameChangeLogs;
use App\Models\UsernameChangeLog;
use App\Support\Time;
use App\Support\UserDisplay;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class UsernameChangeLogResource extends Resource
{
    protected static ?string $model = UsernameChangeLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static string|\UnitEnum|null $navigationGroup = 'User';

    protected static ?int $navigationSort = 100;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.username_change_log');
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
                TextColumn::make('id')->sortable(),
                TextColumn::make('changeTypeText')->label(__('username-change-log.labels.change_type')),
                TextColumn::make('uid')->searchable(),
                TextColumn::make('user.username')->searchable()->label(__('label.username')),
                TextColumn::make('username_old')->searchable()->label(__('username-change-log.labels.username_old')),
                TextColumn::make('username_new')
                    ->searchable()
                    ->label(__('username-change-log.labels.username_new'))
                    ->formatStateUsing(fn ($record) => new HtmlString(UserDisplay::username($record->uid, false, true, true, true))),
                TextColumn::make('operator')
                    ->searchable()
                    ->label(__('label.operator')),
                TextColumn::make('created_at')->label(__('label.created_at'))->formatStateUsing(fn ($state) => Time::formatDateTime($state)),

            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Filter::make('uid')
                    ->schema([
                        TextInput::make('uid')
                            ->label('UID')
                            ->placeholder('UID'),
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['uid'], fn (Builder $query, $uid) => $query->where('uid', $uid));
                    }),
                SelectFilter::make('change_type')->options(UsernameChangeLog::listChangeType())->label(__('username-change-log.labels.change_type')),
            ])
            ->recordActions([
            ])
            ->toolbarActions([
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsernameChangeLogs::route('/'),
        ];
    }
}
