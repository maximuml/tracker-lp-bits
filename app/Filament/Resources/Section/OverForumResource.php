<?php

namespace App\Filament\Resources\Section;

use App\Filament\Resources\Section\OverForumResource\Pages\CreateOverForum;
use App\Filament\Resources\Section\OverForumResource\Pages\EditOverForum;
use App\Filament\Resources\Section\OverForumResource\Pages\ListOverForums;
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

class OverForumResource extends Resource
{
    protected static ?string $model = OverForum::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Section';

    protected static ?int $navigationSort = 21;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.over_forums_manage');
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
                Select::make('minclassview')
                    ->options(User::listClass(0, User::CLASS_SYSOP))
                    ->default(User::CLASS_USER)
                    ->label(__('label.forum.min_class_view')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->searchable()->label(__('label.forum.name'))->sortable(),
                TextColumn::make('sort')->label(__('label.forum.sort'))->sortable(),
                TextColumn::make('forums_count')
                    ->counts('forums')
                    ->label(__('label.forum.sub_forums')),
            ])
            ->defaultSort('sort', 'asc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(fn ($record) => app(ForumRepository::class)->deleteOverforum($record->id)),
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
            'index' => ListOverForums::route('/'),
            'create' => CreateOverForum::route('/create'),
            'edit' => EditOverForum::route('/{record}/edit'),
        ];
    }
}
