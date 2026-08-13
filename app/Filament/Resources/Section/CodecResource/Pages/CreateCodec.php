<?php

namespace App\Filament\Resources\Section\CodecResource\Pages;

use App\Filament\CreateRedirectIndexTrait;
use App\Filament\Resources\Section\CodecResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCodec extends CreateRecord
{
    use CreateRedirectIndexTrait;

    protected static string $resource = CodecResource::class;

    public function afterCreate()
    {
        \App\Support\Cache::clearSearchBox();
        $model = static::$resource::getModel();
        $table = (new $model)->getTable();
        \App\Support\Cache::clearTaxonomy($table);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['mode'] === null) {
            $data['mode'] = 0;
        }
        return $data;
    }
}
