<?php

declare(strict_types=1);

namespace App\Filament\Resources\Security;

use App\Enums\UserClass as UserClassEnum;
use App\Filament\Resources\Security\LoginAttemptResource\Pages\EditLoginAttempt;
use App\Filament\Resources\Security\LoginAttemptResource\Pages\ListLoginAttempts;
use App\Models\LoginAttempt;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class LoginAttemptResource extends Resource
{
    protected static ?string $model = LoginAttempt::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.login_attempts');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->class >= UserClassEnum::SYSOP->value;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ip')->label(__('label.login_attempt.ip'))->required(),
                TextInput::make('attempts')->integer()->label(__('label.login_attempt.attempts'))->default(0),
                Select::make('type')
                    ->options(['login' => 'Login', 'recover' => 'Recover'])
                    ->default('login')
                    ->label(__('label.login_attempt.type')),
                Select::make('banned')
                    ->options([1 => 'Banned', 0 => 'Not banned'])
                    ->default(0)
                    ->label(__('label.login_attempt.status')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('ip')->searchable()->label(__('label.login_attempt.ip'))->sortable(),
                TextColumn::make('attempts')->label(__('label.login_attempt.attempts'))->sortable(),
                TextColumn::make('type')->label(__('label.login_attempt.type'))->badge(),
                TextColumn::make('banned')
                    ->badge()
                    ->colors(['danger' => true, 'success' => false])
                    ->formatStateUsing(fn ($record) => $record->banned ? __('label.login_attempt.banned') : __('label.login_attempt.not_banned'))
                    ->label(__('label.login_attempt.status')),
                TextColumn::make('added')->dateTime('Y-m-d H:i')->sortable()->label(__('label.added')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('banned')
                    ->options([1 => 'Banned', 0 => 'Not banned'])
                    ->label(__('label.login_attempt.status')),
                Tables\Filters\SelectFilter::make('type')
                    ->options(['login' => 'Login', 'recover' => 'Recover'])
                    ->label(__('label.login_attempt.type')),
            ])
            ->recordActions([
                Action::make('ban')
                    ->label(__('label.login_attempt.ban'))
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn ($record) => ! $record->banned)
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['banned' => true])),
                Action::make('unban')
                    ->label(__('label.login_attempt.unban'))
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn ($record) => $record->banned)
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['banned' => false])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
                BulkAction::make('ban_bulk')
                    ->label(__('label.login_attempt.ban_bulk'))
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => $records->each(fn ($r) => $r->update(['banned' => true]))),
                BulkAction::make('unban_bulk')
                    ->label(__('label.login_attempt.unban_bulk'))
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records) => $records->each(fn ($r) => $r->update(['banned' => false]))),
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
            'index' => ListLoginAttempts::route('/'),
            'edit' => EditLoginAttempt::route('/{record}/edit'),
        ];
    }
}
