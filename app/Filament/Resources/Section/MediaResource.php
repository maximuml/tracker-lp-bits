<?php

namespace App\Filament\Resources\Section;

use App\Filament\Resources\Section\MediaResource\Pages\CreateMedia;
use App\Filament\Resources\Section\MediaResource\Pages\EditMedia;
use App\Filament\Resources\Section\MediaResource\Pages\ListMedia;
use App\Models\Media;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MediaResource extends CodecResource
{
    protected static ?string $model = Media::class;

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return parent::form($schema);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table);
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
            'index' => ListMedia::route('/'),
            'create' => CreateMedia::route('/create'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }
}
