<?php

namespace App\Filament\Resources\System;

use App\Filament\OptionsTrait;
use App\Filament\Resources\System\SeedBoxRecordResource\Pages\CreateSeedBoxRecord;
use App\Filament\Resources\System\SeedBoxRecordResource\Pages\EditSeedBoxRecord;
use App\Filament\Resources\System\SeedBoxRecordResource\Pages\ListSeedBoxRecords;
use App\Models\SeedBoxRecord;
use App\Repositories\SeedBoxRepository;
use App\Support\Admin;
use App\Support\Logger;
use App\Support\UserDisplay;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use PhpIP\IP;

class SeedBoxRecordResource extends Resource
{
    use OptionsTrait;

    protected static ?string $model = SeedBoxRecord::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 8;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.seed_box_records');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('operator')->label(__('label.seed_box_record.operator')),
                TextInput::make('bandwidth')->label(__('label.seed_box_record.bandwidth'))->integer(),
                TextInput::make('asn')->label(__('label.seed_box_record.asn'))->integer(),
                //                Forms\Components\TextInput::make('ip_begin')->label(__('label.seed_box_record.ip_begin')),
                //                Forms\Components\TextInput::make('ip_end')->label(__('label.seed_box_record.ip_end')),
                TextInput::make('ip')->label(__('label.seed_box_record.ip'))->helperText(__('label.seed_box_record.ip_help')),
                Toggle::make('is_allowed')
                    ->label(__('label.seed_box_record.is_allowed'))
                    ->helperText(__('label.seed_box_record.is_allowed_help')),
                Textarea::make('comment')->label(__('label.comment')),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('typeText')->label(__('label.seed_box_record.type')),
                TextColumn::make('uid')->searchable(),
                TextColumn::make('user.username')
                    ->label(__('label.username'))
                    ->searchable()
                    ->formatStateUsing(fn ($record) => UserDisplay::adminUsername($record->uid)),
                TextColumn::make('operator')->label(__('label.seed_box_record.operator'))->searchable(),
                TextColumn::make('bandwidth')->label(__('label.seed_box_record.bandwidth')),
                TextColumn::make('asn')->label(__('label.seed_box_record.asn')),
                TextColumn::make('ipRange')
                    ->label(__('label.seed_box_record.ip'))
                    ->searchable(true, function (Builder $query, $search) {
                        try {
                            $ip = IP::create($search);
                            $ipNumeric = $ip->numeric();

                            return $query->orWhere(function (Builder $query) use ($ipNumeric) {
                                return $query->where('ip_begin_numeric', '<=', $ipNumeric)->where('ip_end_numeric', '>=', $ipNumeric);
                            });
                        } catch (Exception $exception) {
                            Logger::writeWithContext((string) ("Invalid IP: {$search}, error: ".$exception->getMessage()), (string) 'info', (bool) false);
                        }
                    }),
                TextColumn::make('comment')->label(__('label.comment'))->searchable(),
                IconColumn::make('is_allowed')->boolean()->label(__('label.seed_box_record.is_allowed')),
                BadgeColumn::make('status')
                    ->colors([
                        'success' => SeedBoxRecord::STATUS_ALLOWED,
                        'warning' => SeedBoxRecord::STATUS_UNAUDITED,
                        'danger' => SeedBoxRecord::STATUS_DENIED,
                    ])
                    ->formatStateUsing(fn ($record) => $record->statusText)
                    ->label(__('label.seed_box_record.status')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Filter::make('id')
                    ->schema([
                        TextInput::make('id')
                            ->label('ID')
                            ->placeholder('ID'),
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['id'], fn (Builder $query, $id) => $query->where('id', $id));
                    }),
                Filter::make('uid')
                    ->schema([
                        TextInput::make('uid')
                            ->label('UID')
                            ->placeholder('UID'),
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['uid'], fn (Builder $query, $uid) => $query->where('uid', $uid));
                    }),
                SelectFilter::make('type')->options(SeedBoxRecord::listTypes('text'))->label(__('label.seed_box_record.type')),
                SelectFilter::make('is_allowed')->options(self::getYesNoOptions())->label(__('label.seed_box_record.is_allowed')),
                SelectFilter::make('status')->options(SeedBoxRecord::listStatus('text'))->label(__('label.seed_box_record.status')),

            ])
            ->recordActions([
                EditAction::make(),
                Action::make('audit')
                    ->label(__('admin.resources.seed_box_record.toggle_status'))
                    ->mountUsing(fn (Schema $schema, SeedBoxRecord $record) => $schema->fill([
                        'status' => $record->status,
                    ]))
                    ->schema([
                        Radio::make('status')
                            ->options(SeedBoxRecord::listStatus('text'))
                            ->inline()
                            ->label(__('label.seed_box_record.status'))
                            ->required(),
                        TextInput::make('reason')
                            ->label(__('label.reason')),
                    ])
                    ->action(function (SeedBoxRecord $record, array $data) {
                        $rep = new SeedBoxRepository;
                        try {
                            $rep->updateStatus($record, $data['status'], $data['reason']);
                        } catch (Exception $exception) {
                            Admin::failNotification(class_basename($exception));
                        }
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
            'index' => ListSeedBoxRecords::route('/'),
            'create' => CreateSeedBoxRecord::route('/create'),
            'edit' => EditSeedBoxRecord::route('/{record}/edit'),
        ];
    }
}
