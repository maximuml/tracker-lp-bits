<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section;

use App\Filament\Resources\Section\AudioCodecResource\Pages\CreateAudioCodec;
use App\Filament\Resources\Section\AudioCodecResource\Pages\EditAudioCodec;
use App\Filament\Resources\Section\AudioCodecResource\Pages\ListAudioCodecs;
use App\Models\AudioCodec;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AudioCodecResource extends CodecResource
{
    protected static ?string $model = AudioCodec::class;

    protected static ?int $navigationSort = 4;

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
            'index' => ListAudioCodecs::route('/'),
            'create' => CreateAudioCodec::route('/create'),
            'edit' => EditAudioCodec::route('/{record}/edit'),
        ];
    }
}
