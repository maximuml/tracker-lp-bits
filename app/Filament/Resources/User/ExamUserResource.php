<?php

namespace App\Filament\Resources\User;

use App\Filament\Resources\User\ExamUserResource\Pages\ListExamUsers;
use App\Filament\Resources\User\ExamUserResource\Pages\ViewExamUser;
use App\Models\Exam;
use App\Models\ExamUser;
use App\Models\User;
use App\Repositories\ExamRepository;
use App\Support\Logger;
use App\Support\UserDisplay;
use Carbon\Carbon;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ExamUserResource extends Resource
{
    protected static ?string $model = ExamUser::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'User';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.exam_users');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

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
                TextColumn::make('exam.name')->label(__('label.exam.label')),
                TextColumn::make('exam.typeText')->label(__('exam.type')),
                TextColumn::make('begin')->label(__('label.begin'))->dateTime(),
                TextColumn::make('end')->label(__('label.end'))->dateTime(),
                BooleanColumn::make('is_done')->label(__('label.exam_user.is_done')),
                TextColumn::make('statusText')->label(__('label.status')),
                TextColumn::make('created_at')->dateTime()->label(__('label.created_at')),
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
                SelectFilter::make('exam_type')
                    ->options(Exam::listTypeOptions())
                    ->label(__('exam.type'))
                    ->query(function (Builder $query, array $data) {
                        $query->when($data['value'], function (Builder $query) use ($data) {
                            $query->whereHas('exam', function (Builder $query) use ($data) {
                                $query->where('type', $data['value']);
                            });
                        });
                    }),
                SelectFilter::make('exam_id')
                    ->options(Exam::query()->pluck('name', 'id')->toArray())
                    ->label(__('label.exam.label')),
                SelectFilter::make('status')->options(ExamUser::listStatus(true))->label(__('label.status')),
                SelectFilter::make('is_done')->options(['0' => 'No', '1' => 'yes'])->label(__('label.exam_user.is_done')),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->groupedBulkActions([
                BulkAction::make('Avoid')->action(function (Collection $records) {
                    $idArr = $records->pluck('id')->toArray();
                    $user = Auth::user();
                    if (! $user instanceof User) {
                        throw new \RuntimeException('Expected an authenticated user.');
                    }
                    $rep = new ExamRepository;
                    $rep->avoidExamUserBulk(['id' => $idArr], $user);
                })
                    ->deselectRecordsAfterCompletion()
                    ->requiresConfirmation()
                    ->label(__('admin.resources.exam_user.bulk_action_avoid_label'))
                    ->icon('heroicon-o-x-mark'),

                BulkAction::make('UpdateEnd')
                    ->form([
                        DateTimePicker::make('end')
                            ->required()
                            ->label(__('label.end')),
                        Textarea::make('reason')
                            ->label(__('label.reason')),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $end = Carbon::parse($data['end']);
                        $rep = new ExamRepository;
                        foreach ($records as $record) {
                            if ($end->isAfter($record->begin)) {
                                $rep->updateExamUserEnd($record, $end, $data['reason'] ?? '');
                            } else {
                                Logger::writeWithContext((string) sprintf('examUser: %d end: %s is before begin: %s, skip', $record->id, $end, $record->begin), (string) 'info', (bool) false);
                            }
                        }
                    })
                    ->deselectRecordsAfterCompletion()
                    ->label(__('admin.resources.exam_user.bulk_action_update_end_label'))
                    ->icon('heroicon-o-pencil'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)->schema([
                    Group::make([
                        TextEntry::make('id'),
                        TextEntry::make('statusText')
                            ->label(__('label.status')),
                        TextEntry::make('uid')
                            ->formatStateUsing(fn ($record) => UserDisplay::adminUsername($record->uid))
                            ->label(__('label.username')),
                        TextEntry::make('exam.name')
                            ->label(__('label.exam.label')),
                        TextEntry::make('begin')
                            ->label(__('label.begin')),
                        TextEntry::make('end')
                            ->label(__('label.end')),
                        TextEntry::make('isDoneText')
                            ->label(__('label.exam_user.is_done')),
                        TextEntry::make('created_at')
                            ->label(__('label.created_at')),
                        TextEntry::make('updated_at')
                            ->label(__('label.updated_at')),
                    ])
                        ->columnSpan(1)
                        ->columns(2),
                    Group::make([
                        RepeatableEntry::make('progressFormatted')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make(__('label.exam.index_required_label')),
                                TableColumn::make(__('label.exam.index_required_value')),
                                TableColumn::make(__('label.exam.index_current_value')),
                                TableColumn::make(__('label.exam.index_result')),
                            ])
                            ->schema([
                                TextEntry::make('index_formatted'),
                                TextEntry::make('require_value_formatted'),
                                TextEntry::make('current_value_formatted'),
                                TextEntry::make('index_result')->html(),
                            ]),
                    ])->columnSpan(1),
                ]),
            ]);

    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'exam']);
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
            'index' => ListExamUsers::route('/'),
            'view' => ViewExamUser::route('/{record}'),
        ];
    }
}
