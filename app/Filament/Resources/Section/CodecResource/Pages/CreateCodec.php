<?php

namespace App\Filament\Resources\Section\CodecResource\Pages;

use App\Filament\CreateRedirectIndexTrait;
use App\Filament\Resources\Section\CodecResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCodec extends CreateRecord
{
    use CreateRedirectIndexTrait;

    protected static string $resource = CodecResource::class;

    public function afterCreate(): void
    {
        \App\Support\Cache::clearSearchBox();
        $model = static::$resource::getModel();
        if ($model === null || ! is_a($model, Model::class, true)) {
            throw new \RuntimeException('Invalid model class.');
        }
        $table = (new $model)->getTable();
        \App\Support\Cache::clearTaxonomy($table);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return  array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['mode'] === null) {
            $data['mode'] = 0;
        }
        return $data;
    }
}
