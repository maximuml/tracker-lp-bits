<?php

declare(strict_types=1);

namespace App\Filament\Resources\Security;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserClass as UserClassEnum;
use App\Filament\Resources\Security\StaffMessageResource\Pages\ListStaffMessages;
use App\Filament\Resources\Security\StaffMessageResource\Pages\ViewStaffMessage;
use App\Models\StaffMessage;
use App\Models\User;
use App\Repositories\ToolRepository;
use App\Support\Cache;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class StaffMessageResource extends Resource
{
    protected static ?string $model = StaffMessage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.staff_messages');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->class >= UserClassEnum::MODERATOR->value;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        if ($user instanceof User && Permission::can(PermissionEnum::STAFF_MEMBER, $user)) {
            return $query;
        }
        // Non-staff-member users can only see messages whose permission they hold
        $userPerms = ToolRepository::listUserAllPermissions((int) $user?->id);

        return $query->where(function (Builder $q) use ($userPerms) {
            $q->whereNull('permission')->orWhereIn('permission', $userPerms);
        });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject')->label(__('label.staff_message.subject'))->disabled(),
                TextInput::make('sender')->label(__('label.staff_message.sender'))->disabled(),
                Textarea::make('msg')->label(__('label.staff_message.message'))->rows(4)->disabled(),
                Textarea::make('answer')->label(__('label.staff_message.answer'))->rows(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('subject')->searchable()->label(__('label.staff_message.subject'))->limit(40),
                TextColumn::make('send_user.username')->label(__('label.staff_message.sender')),
                TextColumn::make('answered')
                    ->badge()
                    ->colors(['danger' => 0, 'success' => 1])
                    ->formatStateUsing(fn ($record) => $record->answered ? __('label.staff_message.answered') : __('label.staff_message.unanswered'))
                    ->label(__('label.staff_message.status')),
                TextColumn::make('answer_user.username')->label(__('label.staff_message.answered_by'))->placeholder('N/A'),
                TextColumn::make('added')->dateTime('Y-m-d H:i')->sortable()->label(__('label.added')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('answered')
                    ->options([0 => __('label.staff_message.unanswered'), 1 => __('label.staff_message.answered')])
                    ->label(__('label.staff_message.status'))
                    ->query(fn (Builder $query, array $data) => $data['value'] !== null ? $query->where('answered', $data['value']) : $query),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('label.staff_message.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => StaffMessageResource::getUrl('view', ['record' => $record->id])),
                Action::make('mark_answered')
                    ->label(__('label.staff_message.mark_answered'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->answered == 0)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['answered' => 1, 'answeredby' => Auth::id() ?? 0]);
                        Cache::clearStaffMessage();
                    }),
                DeleteAction::make()
                    ->after(fn () => Cache::clearStaffMessage()),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->after(fn () => Cache::clearStaffMessage()),
                BulkAction::make('mark_answered_bulk')
                    ->label(__('label.staff_message.mark_answered_bulk'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        $records->where('answered', 0)->each(function ($record) {
                            $record->update(['answered' => 1, 'answeredby' => Auth::id() ?? 0]);
                        });
                        Cache::clearStaffMessage();
                    }),
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
            'index' => ListStaffMessages::route('/'),
            'view' => ViewStaffMessage::route('/{record}'),
        ];
    }
}
