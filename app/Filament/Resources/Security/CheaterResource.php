<?php

declare(strict_types=1);

namespace App\Filament\Resources\Security;

use App\Enums\UserClass as UserClassEnum;
use App\Filament\Resources\Security\CheaterResource\Pages\ListCheaters;
use App\Models\Cheater;
use App\Models\User;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CheaterResource extends Resource
{
    protected static ?string $model = Cheater::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.cheaters');
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('userid')->integer()->label(__('label.cheater.userid')),
                Forms\Components\TextInput::make('torrentid')->integer()->label(__('label.cheater.torrentid')),
                Forms\Components\Textarea::make('comment')->label(__('label.cheater.comment'))->rows(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('userid')->label(__('label.cheater.userid'))->sortable(),
                TextColumn::make('torrent.name')
                    ->label(__('label.cheater.torrent'))
                    ->limit(40)
                    ->url(fn ($record) => $record->torrentid > 0 ? "/details/{$record->torrentid}" : null),
                TextColumn::make('uploaded')->label(__('label.cheater.uploaded'))
                    ->formatStateUsing(fn ($record) => Format::size((int) $record->uploaded)),
                TextColumn::make('downloaded')->label(__('label.cheater.downloaded'))
                    ->formatStateUsing(fn ($record) => Format::size((int) $record->downloaded)),
                TextColumn::make('anctime')->label(__('label.cheater.anctime'))->sortable(),
                TextColumn::make('dealtwith')
                    ->badge()
                    ->colors(['danger' => 0, 'success' => 1])
                    ->formatStateUsing(fn ($record) => $record->dealtwith ? __('label.cheater.dealt') : __('label.cheater.undealt'))
                    ->label(__('label.cheater.status')),
                TextColumn::make('dealtby')->label(__('label.cheater.dealtby'))->placeholder('N/A'),
                TextColumn::make('added')->dateTime('Y-m-d H:i')->sortable()->label(__('label.added')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('dealtwith')
                    ->options([0 => __('label.cheater.undealt'), 1 => __('label.cheater.dealt')])
                    ->label(__('label.cheater.status'))
                    ->query(fn (Builder $query, array $data) => $data['value'] !== null ? $query->where('dealtwith', $data['value']) : $query),
            ])
            ->recordActions([
                Action::make('mark_dealt')
                    ->label(__('label.cheater.mark_dealt'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record) => $record->dealtwith == 0)
                    ->action(function ($record) {
                        $record->update([
                            'dealtwith' => 1,
                            'dealtby' => Auth::id() ?? 0,
                        ]);
                        $cache = app(LegacyRedisCache::class);
                        $cache?->delete_value('staff_new_cheater_count', true);
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
                BulkAction::make('mark_dealt_bulk')
                    ->label(__('label.cheater.mark_dealt_bulk'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        $records->where('dealtwith', 0)->each(function ($record) {
                            $record->update([
                                'dealtwith' => 1,
                                'dealtby' => Auth::id() ?? 0,
                            ]);
                        });
                        $cache = app(LegacyRedisCache::class);
                        $cache?->delete_value('staff_new_cheater_count', true);
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
            'index' => ListCheaters::route('/'),
        ];
    }
}
