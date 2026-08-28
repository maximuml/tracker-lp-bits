<?php

declare(strict_types=1);

namespace App\Filament\Resources\User;

use App\Filament\OptionsTrait;
use App\Filament\Resources\User\UserMedalResource\Pages\CreateUserMedal;
use App\Filament\Resources\User\UserMedalResource\Pages\EditUserMedal;
use App\Filament\Resources\User\UserMedalResource\Pages\ListUserMedals;
use App\Models\Medal;
use App\Models\UserMedal;
use App\Repositories\MedalRepository;
use App\Support\Admin;
use App\Support\Cache;
use App\Support\UserDisplay;
use Carbon\Carbon;
use Exception;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class UserMedalResource extends Resource
{
    use OptionsTrait;

    protected static ?string $model = UserMedal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static string|\UnitEnum|null $navigationGroup = 'User';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.users_medals');
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
                TextColumn::make('uid')->searchable(),
                TextColumn::make('user.username')
                    ->label(__('label.username'))
                    ->searchable()
                    ->formatStateUsing(fn ($record) => new HtmlString(UserDisplay::username($record->uid, false, true, true, true))),
                TextColumn::make('medal.name')->label(__('label.medal.label'))->searchable(),
                ImageColumn::make('medal.image_large')->label(__('label.image')),
                TextColumn::make('expire_at')->label(__('label.expire_at')),
                TextColumn::make('bonus_addition_expire_at')->label(__('medal.bonus_addition_expire_at')),
                TextColumn::make('wearingStatusText')->label(__('label.status')),
                TextColumn::make('created_at')->label(__('label.created_at')),
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
                SelectFilter::make('medal_id')
                    ->options(Medal::query()->pluck('name', 'id')->toArray())
                    ->label(__('medal.label')),
                SelectFilter::make('is_expired')
                    ->options(self::getYesNoOptions())
                    ->label(__('medal.is_expired'))
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['value']) && $data['value'] === '0') {
                            // 未过期，为 null 或大于当前时间
                            $query->where(function ($query) {
                                $query->whereNull('expire_at')->orWhere('expire_at', '>', now());
                            });
                        }
                        if (isset($data['value']) && $data['value'] === '1') {
                            // 已过期, 不为 null 且小于当前时间
                            $query->whereNotNull('expire_at')->where('expire_at', '<', now());
                        }
                    }),
                SelectFilter::make('is_bonus_addition_expired')
                    ->options(self::getYesNoOptions())
                    ->label(__('medal.is_bonus_addition_expired'))
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['value']) && $data['value'] === '0') {
                            // 未过期，为 null 或大于当前时间
                            $query->where(function ($query) {
                                $query->whereNull('bonus_addition_expire_at')->orWhere('bonus_addition_expire_at', '>', now());
                            });
                        }
                        if (isset($data['value']) && $data['value'] === '1') {
                            // 已过期, 不为 null 且小于当前时间
                            $query->whereNotNull('bonus_addition_expire_at')->where('bonus_addition_expire_at', '<', now());
                        }
                    }),
                SelectFilter::make('status')
                    ->options(UserMedal::listWearingStatusLabels())
                    ->label(__('label.status')),
            ])
            ->recordActions([
                DeleteAction::make()->using(function (UserMedal $record) {
                    $record->delete();
                    Cache::clearUser($record->uid, '');
                    Admin::successNotification('');
                }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::buildBulkActionIncreaseExpireAt('expire_at'),
                    self::buildBulkActionUpdateExpireAt('expire_at'),
                    self::buildBulkActionCancelExpireAt('expire_at'),
                ])->label(sprintf('%s-%s', __('label.bulk'), __('label.expire_at'))),
                BulkActionGroup::make([
                    self::buildBulkActionIncreaseExpireAt('bonus_addition_expire_at'),
                    self::buildBulkActionUpdateExpireAt('bonus_addition_expire_at'),
                    self::buildBulkActionCancelExpireAt('bonus_addition_expire_at'),
                ])->label(sprintf('%s-%s', __('label.bulk'), __('medal.bonus_addition_expire_at'))),
                DeleteBulkAction::make(),
            ])
            ->selectCurrentPageOnly();
    }

    private static function buildBulkActionIncreaseExpireAt(string $filed): BulkAction
    {
        return BulkAction::make("bulkActionIncrease$filed")
            ->label(__('medal.bulk_action_increase'))
            ->modalHeading(__('medal.bulk_action_increase_'.$filed))
            ->form([
                TextInput::make('increase_duration')
                    ->label(__('medal.increase_duration'))
                    ->helperText(__('medal.increase_duration_help'))
                    ->integer()
                    ->required(),
            ])
            ->action(function (Collection $collection, array $data) use ($filed) {
                try {
                    $rep = app(MedalRepository::class);
                    $rep->increaseExpireAt($collection, $filed, $data['increase_duration']);
                    Admin::successNotification('');
                } catch (Exception $e) {
                    Admin::failNotification($e->getMessage());
                }
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function buildBulkActionUpdateExpireAt(string $filed): BulkAction
    {
        return BulkAction::make("bulkActionUpdate$filed")
            ->label(__('medal.bulk_action_update'))
            ->modalHeading(__('medal.bulk_action_update_'.$filed))
            ->form([
                DateTimePicker::make('update_expire_at')
                    ->label(__('medal.update_expire_at'))
                    ->helperText(__('medal.update_expire_at_help'))
                    ->required(),
            ])
            ->action(function (Collection $collection, array $data) use ($filed) {
                try {
                    $expireAt = Carbon::parse($data['update_expire_at']);
                    $rep = app(MedalRepository::class);
                    $rep->updateExpireAt($collection, $filed, $expireAt);
                    Admin::successNotification('');
                } catch (Exception $e) {
                    Admin::failNotification($e->getMessage());
                }
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function buildBulkActionCancelExpireAt(string $filed): BulkAction
    {
        return BulkAction::make("bulkActionCancel$filed")
            ->label(__('medal.bulk_action_cancel'))
            ->modalHeading(__('medal.bulk_action_cancel_'.$filed))
            ->requiresConfirmation()
            ->action(function (Collection $collection) use ($filed) {
                try {
                    $rep = app(MedalRepository::class);
                    $rep->cancelExpireAt($collection, $filed);
                    Admin::successNotification('');
                } catch (Exception $e) {
                    Admin::failNotification($e->getMessage());
                }
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'medal']);
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
            'index' => ListUserMedals::route('/'),
            'create' => CreateUserMedal::route('/create'),
            'edit' => EditUserMedal::route('/{record}/edit'),
        ];
    }
}
