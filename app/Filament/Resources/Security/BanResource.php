<?php

namespace App\Filament\Resources\Security;

use App\Filament\Resources\Security\BanResource\Pages\CreateBan;
use App\Filament\Resources\Security\BanResource\Pages\EditBan;
use App\Filament\Resources\Security\BanResource\Pages\ListBans;
use App\Models\Ban;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BanResource extends Resource
{
    protected static ?string $model = Ban::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.ip_bans');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->class >= User::CLASS_ADMINISTRATOR;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_ip')
                    ->label(__('label.ban.first_ip'))
                    ->required()
                    ->ip()
                    ->helperText(__('label.ban.first_ip_help'))
                    ->dehydrateStateUsing(fn ($state) => Ban::ipToLong($state) ?: 0)
                    ->formatStateUsing(fn ($record) => $record && $record->first > 0 ? Ban::longToIp((int) $record->first) : ''),
                TextInput::make('last_ip')
                    ->label(__('label.ban.last_ip'))
                    ->required()
                    ->ip()
                    ->helperText(__('label.ban.last_ip_help'))
                    ->dehydrateStateUsing(fn ($state) => Ban::ipToLong($state) ?: 0)
                    ->formatStateUsing(fn ($record) => $record && $record->last > 0 ? Ban::longToIp((int) $record->last) : ''),
                Textarea::make('comment')
                    ->label(__('label.ban.comment'))
                    ->required()
                    ->rows(2),
                Forms\Components\Hidden::make('addedby')
                    ->default(fn () => Auth::id() ?? 0),
                Forms\Components\Hidden::make('added')
                    ->default(fn () => date('Y-m-d H:i:s')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('ip_range')
                    ->label(__('label.ban.ip_range'))
                    ->state(fn ($record) => Ban::longToIp((int) $record->first).' - '.Ban::longToIp((int) $record->last)),
                TextColumn::make('comment')->label(__('label.ban.comment'))->limit(50),
                TextColumn::make('addedByUser.username')
                    ->label(__('label.ban.addedby'))
                    ->placeholder('N/A'),
                TextColumn::make('added')->dateTime('Y-m-d H:i')->sortable()->label(__('label.added')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => ListBans::route('/'),
            'create' => CreateBan::route('/create'),
            'edit' => EditBan::route('/{record}/edit'),
        ];
    }
}
