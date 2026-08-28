<?php

declare(strict_types=1);

namespace App\Filament\Resources\Torrent;

use App\Auth\Permission;
use App\Filament\OptionsTrait;
use App\Filament\Resources\Torrent\TorrentResource\Pages\CreateTorrent;
use App\Filament\Resources\Torrent\TorrentResource\Pages\EditTorrent;
use App\Filament\Resources\Torrent\TorrentResource\Pages\ListTorrents;
use App\Models\Category;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Repositories\TagRepository;
use App\Repositories\TorrentRepository;
use App\Support\Format;
use App\Support\Logger;
use App\Support\TorrentAccess;
use App\Support\TorrentOps;
use App\Support\UserDisplay;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TorrentResource extends Resource
{
    use OptionsTrait;

    protected static ?string $model = Torrent::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Torrent';

    protected static ?int $navigationSort = 1;

    private static ?TorrentRepository $rep = null;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.torrent_list');
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

    public static function getRep(): TorrentRepository
    {
        if (self::$rep === null) {
            self::$rep = app(TorrentRepository::class);
        }

        return self::$rep;
    }

    public static function table(Table $table): Table
    {
        $showApproval = self::shouldShowApproval();

        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('basic_category.name')->label(__('label.torrent.category')),
                TextColumn::make('name')->formatStateUsing(fn ($record) => TorrentAccess::adminName($record, true))
                    ->label(__('label.name'))
                    ->searchable(query: function (Builder $query, string $search) {
                        return $query->where('name', 'like', "%{$search}%");
                    }),
                TextColumn::make('posStateText')->label(__('label.torrent.pos_state')),
                TextColumn::make('spStateText')->label(__('label.torrent.sp_state')),
                IconColumn::make('hr')
                    ->label(__('label.torrent.hr'))
                    ->boolean(),
                TextColumn::make('size')
                    ->label(__('label.torrent.size'))
                    ->formatStateUsing(fn ($state) => Format::size($state))
                    ->sortable(),
                TextColumn::make('seeders')->label(__('label.torrent.seeders'))->sortable(),
                TextColumn::make('leechers')->label(__('label.torrent.leechers'))->sortable(),
                BadgeColumn::make('approval_status')
                    ->visible($showApproval)
                    ->label(__('label.torrent.approval_status'))
                    ->colors(array_flip(Torrent::listApprovalStatus(true, 'badge_color')))
                    ->formatStateUsing(fn ($record) => $record->approvalStatusText),
                TextColumn::make('added')->label(__('label.added'))->dateTime(),
                TextColumn::make('user.username')
                    ->label(__('label.torrent.owner'))
                    ->formatStateUsing(fn ($record) => UserDisplay::adminUsername($record->owner)),
            ])
            ->defaultSort('id', 'desc')
            ->filters(self::getFilters())
            ->recordActions(self::getActions())
            ->toolbarActions(self::getBulkActions());

    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'basic_category', 'tags']);
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
            'index' => ListTorrents::route('/'),
            'create' => CreateTorrent::route('/create'),
            'edit' => EditTorrent::route('/{record}/edit'),
        ];
    }

    /** @return array<BulkAction> */
    private static function getBulkActions(): array
    {
        $actions = [];
        if (Permission::canSetTorrentPosState()) {
            $actions[] = BulkAction::make('posState')
                ->label(__('admin.resources.torrent.bulk_action_pos_state'))
                ->form([
                    Select::make('pos_state')
                        ->label(__('label.torrent.pos_state'))
                        ->options(Torrent::listPosStates(true))
                        ->required(),
                    DateTimePicker::make('pos_state_until')
                        ->label(__('label.deadline')),
                ])
                ->icon('heroicon-o-arrow-up-circle')
                ->action(function (Collection $records, array $data) {
                    $idArr = $records->pluck('id')->toArray();
                    try {
                        $torrentRep = app(TorrentRepository::class);
                        $torrentRep->setPosState($idArr, $data['pos_state'], $data['pos_state_until']);
                    } catch (Exception $exception) {
                        Logger::writeWithContext((string) ($exception->getMessage().$exception->getTraceAsString()), (string) 'error', (bool) false);
                        Notification::make()->danger()->body(class_basename($exception))->send();
                    }
                })
                ->deselectRecordsAfterCompletion();
        }

        if (Permission::canSetTorrentOnPromotion()) {
            $actions[] = BulkAction::make('sp_state')
                ->label(__('admin.resources.torrent.bulk_action_sp_state'))
                ->form([
                    Select::make('sp_state')
                        ->label(__('label.torrent.sp_state'))
                        ->options(Torrent::listPromotionTypes(true))
                        ->required(),
                    Select::make('promotion_time_type')
                        ->label(__('label.torrent.promotion_time_type'))
                        ->options(Torrent::listPromotionTimeTypes(true))
                        ->required(),
                    DateTimePicker::make('promotion_until')
                        ->label(__('label.deadline')),
                ])
                ->icon('heroicon-o-megaphone')
                ->action(function (Collection $records, array $data) {
                    $idArr = $records->pluck('id')->toArray();
                    try {
                        $torrentRep = app(TorrentRepository::class);
                        $torrentRep->setSpState($idArr, $data['sp_state'], $data['promotion_time_type'], $data['promotion_until']);
                    } catch (Exception $exception) {
                        Logger::writeWithContext((string) ($exception->getMessage().$exception->getTraceAsString()), (string) 'error', (bool) false);
                        Notification::make()->danger()->body($exception->getMessage())->send();
                    }
                })
                ->deselectRecordsAfterCompletion();
        }

        if (Permission::canManageTorrent()) {
            $actions[] = BulkAction::make('remove_tag')
                ->label(__('admin.resources.torrent.bulk_action_remove_tag'))
                ->requiresConfirmation()
                ->icon('heroicon-o-minus-circle')
                ->action(function (Collection $records) {
                    $idArr = $records->pluck('id')->toArray();
                    try {
                        $torrentRep = app(TorrentRepository::class);
                        $torrentRep->syncTags($idArr);
                    } catch (Exception $exception) {
                        Logger::writeWithContext((string) ($exception->getMessage().$exception->getTraceAsString()), (string) 'error', (bool) false);
                        Notification::make()->danger()->body(class_basename($exception))->send();
                    }
                })
                ->deselectRecordsAfterCompletion();

            $actions[] = BulkAction::make('attach_tag')
                ->label(__('admin.resources.torrent.bulk_action_attach_tag'))
                ->form([
                    Checkbox::make('remove')->label(__('admin.resources.torrent.bulk_action_attach_tag_remove_old')),
                    CheckboxList::make('tags')
                        ->label(__('label.tag.label'))
                        ->columns(4)
                        ->options(TagRepository::createBasicQuery()->pluck('name', 'id')->toArray())
                        ->required(),

                ])
                ->icon('heroicon-o-tag')
                ->action(function (Collection $records, array $data) {
                    if (empty($data['tags'])) {
                        return;
                    }
                    $idArr = $records->pluck('id')->toArray();
                    try {
                        $torrentRep = app(TorrentRepository::class);
                        $torrentRep->syncTags($idArr, $data['tags'], $data['remove'] ?? false);
                    } catch (Exception $exception) {
                        Logger::writeWithContext((string) ($exception->getMessage().$exception->getTraceAsString()), (string) 'error', (bool) false);
                        Notification::make()->danger()->body(class_basename($exception))->send();
                    }
                })
                ->deselectRecordsAfterCompletion();

            $actions[] = BulkAction::make('hr')
                ->label(__('admin.resources.torrent.bulk_action_hr'))
                ->form([
                    Radio::make('hr')
                        ->label(__('admin.resources.torrent.bulk_action_hr'))
                        ->inline()
                        ->options(self::getYesNoOptions())
                        ->required(),

                ])
                ->icon('heroicon-o-sparkles')
                ->action(function (Collection $records, array $data) {
                    if (! isset($data['hr'])) {
                        return;
                    }
                    $idArr = $records->pluck('id')->toArray();
                    try {
                        $torrentRep = app(TorrentRepository::class);
                        $torrentRep->setHr($idArr, $data['hr']);
                    } catch (Exception $exception) {
                        Logger::writeWithContext((string) ($exception->getMessage().$exception->getTraceAsString()), (string) 'error', (bool) false);
                        Notification::make()->danger()->body(class_basename($exception))->send();
                    }
                })
                ->deselectRecordsAfterCompletion();
        }

        if (Permission::canDeleteTorrent()) {
            $actions[] = DeleteBulkAction::make('bulk-delete')->using(function (Collection $records) {
                TorrentOps::deleteTorrents($records->pluck('id')->toArray(), (bool) false);
            });
        }

        return $actions;
    }

    /** @return array<Action> */
    private static function getActions(): array
    {
        $actions = [];
        if (self::shouldShowApproval() && Permission::canApproveTorrent()) {
            $actions[] = Action::make('approval')
                ->label(__('admin.resources.torrent.action_approval'))
                ->schema([
                    Radio::make('approval_status')
                        ->label(__('label.torrent.approval_status'))
                        ->inline()
                        ->required()
                        ->options(Torrent::listApprovalStatus(true)),
                    Textarea::make('comment')->label(__('label.comment')),
                ])
                ->action(function (Torrent $record, array $data) {
                    $torrentRep = app(TorrentRepository::class);
                    try {
                        $data['torrent_id'] = $record->id;
                        $torrentRep->approval(Auth::user(), $data);
                    } catch (Exception $exception) {
                        Logger::writeWithContext((string) $exception->getMessage(), (string) 'error', (bool) false);
                    }
                });

        }
        if (Permission::canDeleteTorrent()) {
            $actions[] = DeleteAction::make('delete')->using(function ($record) {
                TorrentOps::deleteTorrents($record->id, (bool) false);
            });
        }

        return $actions;
    }

    private static function shouldShowApproval(): bool
    {
        return false;
    }

    /** @return array<Filter|SelectFilter> */
    private static function getFilters(): array
    {
        $filters = [
            Filter::make('owner')
                ->schema([
                    TextInput::make('owner')
                        ->label(__('label.torrent.owner'))
                        ->placeholder('UID'),
                ])->query(function (Builder $query, array $data) {
                    return $query->when($data['owner'], fn (Builder $query, $owner) => $query->where('owner', $owner));
                }),

            SelectFilter::make('visible')
                ->options(self::$yesOrNo)
                ->label(__('label.torrent.visible')),
            SelectFilter::make('hr')
                ->options(self::getYesNoOptions())
                ->label(__('label.torrent.hr')),

            SelectFilter::make('pos_state')
                ->options(Torrent::listPosStates(true))
                ->label(__('label.torrent.pos_state'))
                ->multiple(),

            SelectFilter::make('sp_state')
                ->options(Torrent::listPromotionTypes(true))
                ->label(__('label.torrent.sp_state'))
                ->multiple(),

            SelectFilter::make('approval_status')
                ->options(Torrent::listApprovalStatus(true))
                ->label(__('label.torrent.approval_status'))
                ->multiple(),

            SelectFilter::make('tags')
                ->relationship('tags', 'name')
                ->label(__('label.tag.label'))
                ->multiple(),
            SelectFilter::make('category')
                ->options(Category::query()->pluck('name', 'id')->toArray())
                ->label(__('label.torrent.category'))
                ->multiple(),
        ];
        foreach (SearchBox::$taxonomies as $torrentField => $tableModel) {
            $filters[] = SelectFilter::make((string) $torrentField)
                ->options(DB::table((string) $tableModel['table'])->orderBy('sort_index')->orderBy('id')->pluck('name', 'id'))
                ->multiple();
        }

        $filters[] = Filter::make('added_begin')
            ->schema([
                DatePicker::make('added_begin')
                    ->maxDate(now())
                    ->label(__('label.torrent.added_begin')),
            ])->query(function (Builder $query, array $data) {
                return $query->when($data['added_begin'], fn (Builder $query, $value) => $query->where('added', '>=', $value));
            });
        $filters[] = Filter::make('added_end')
            ->schema([
                DatePicker::make('added_end')
                    ->maxDate(now())
                    ->label(__('label.torrent.added_end')),
            ])->query(function (Builder $query, array $data) {
                return $query->when($data['added_end'], fn (Builder $query, $value) => $query->where('added', '<=', $value));
            });
        $filters[] = Filter::make('size_begin')
            ->schema([
                TextInput::make('size_begin')
                    ->numeric()
                    ->placeholder('GB')
                    ->label(__('label.torrent.size_begin')),
            ])->query(function (Builder $query, array $data) {
                return $query->when($data['size_begin'], fn (Builder $query, $value) => $query->where('size', '>=', $value * 1024 * 1024 * 1024));
            });
        $filters[] = Filter::make('size_end')
            ->schema([
                TextInput::make('size_end')
                    ->numeric()
                    ->placeholder('GB')
                    ->label(__('label.torrent.size_end')),
            ])->query(function (Builder $query, array $data) {
                return $query->when($data['size_end'], fn (Builder $query, $value) => $query->where('size', '<=', $value * 1024 * 1024 * 1024));
            });

        return $filters;

    }
}
