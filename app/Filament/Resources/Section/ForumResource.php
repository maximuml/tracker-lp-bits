<?php

namespace App\Filament\Resources\Section;

use App\Filament\Resources\Section\ForumResource\Pages\CreateForum;
use App\Filament\Resources\Section\ForumResource\Pages\EditForum;
use App\Filament\Resources\Section\ForumResource\Pages\ListForums;
use App\Models\Forum;
use App\Models\OverForum;
use App\Models\User;
use App\Repositories\ForumRepository;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ForumResource extends Resource
{
    protected static ?string $model = Forum::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Section';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.forums_manage');
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
                TextInput::make('name')->required()->label(__('label.forum.name')),
                Textarea::make('description')->label(__('label.forum.description'))->rows(3),
                TextInput::make('sort')->integer()->default(0)->label(__('label.forum.sort')),
                Select::make('forid')
                    ->options(OverForum::query()->pluck('name', 'id')->toArray())
                    ->label(__('label.forum.over_forum')),
                Select::make('minclassread')
                    ->options(User::listClass(0, User::CLASS_SYSOP))
                    ->default(User::CLASS_USER)
                    ->label(__('label.forum.min_class_read')),
                Select::make('minclasswrite')
                    ->options(User::listClass(0, User::CLASS_SYSOP))
                    ->default(User::CLASS_USER)
                    ->label(__('label.forum.min_class_write')),
                Select::make('minclasscreate')
                    ->options(User::listClass(0, User::CLASS_SYSOP))
                    ->default(User::CLASS_USER)
                    ->label(__('label.forum.min_class_create')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->searchable()->label(__('label.forum.name'))->sortable(),
                TextColumn::make('overForum.name')->label(__('label.forum.over_forum'))->placeholder('—'),
                TextColumn::make('sort')->label(__('label.forum.sort'))->sortable(),
                TextColumn::make('topiccount')->label(__('label.forum.topics'))->sortable(),
                TextColumn::make('postcount')->label(__('label.forum.posts'))->sortable(),
            ])
            ->defaultSort('sort', 'asc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(fn ($record) => app(ForumRepository::class)->deleteForum($record->id)),
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
            'index' => ListForums::route('/'),
            'create' => CreateForum::route('/create'),
            'edit' => EditForum::route('/{record}/edit'),
        ];
    }
}
