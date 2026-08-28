<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section;

use App\Filament\Resources\Section\StandardResource\Pages\CreateStandard;
use App\Filament\Resources\Section\StandardResource\Pages\EditStandard;
use App\Filament\Resources\Section\StandardResource\Pages\ListStandards;
use App\Models\Standard;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class StandardResource extends CodecResource
{
    protected static ?string $model = Standard::class;

    protected static ?int $navigationSort = 5;

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
            'index' => ListStandards::route('/'),
            'create' => CreateStandard::route('/create'),
            'edit' => EditStandard::route('/{record}/edit'),
        ];
    }
}
