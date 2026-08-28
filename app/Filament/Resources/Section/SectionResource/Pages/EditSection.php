<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\SectionResource\Pages;

use App\Filament\Resources\Section\SectionResource;
use App\Models\SearchBox;
use App\Support\Cache;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSection extends EditRecord
{
    protected static string $resource = SectionResource::class;

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return SearchBox::formatTaxonomyExtra($data);
    }

    protected function afterSave(): void
    {
        Cache::clearSearchBox();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (SearchBox::$extras as $field => $text) {
            if (! empty($data['extra'][$field])) {
                $data['other'][] = $field;
            }
            unset($data['extra'][$field]);
        }

        return $data;
    }
}
