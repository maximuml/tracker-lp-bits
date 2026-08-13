<?php

namespace App\Filament\Resources\Section\CodecResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\EditRedirectIndexTrait;
use App\Filament\Resources\Section\CodecResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCodec extends EditRecord
{
    use EditRedirectIndexTrait;

    protected static string $resource = CodecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function afterSave()
    {
        \App\Support\Cache::clearSearchBox();
        $model = static::$resource::getModel();
        $table = (new $model)->getTable();
        \App\Support\Cache::clearTaxonomy($table);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['mode'] === null) {
            $data['mode'] = 0;
        }
        return $data;
    }

}
