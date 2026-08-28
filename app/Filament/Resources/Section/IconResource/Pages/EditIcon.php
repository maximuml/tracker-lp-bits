<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\IconResource\Pages;

use App\Filament\EditRedirectIndexTrait;
use App\Filament\Resources\Section\IconResource;
use App\Support\Cache;
use App\Support\Locale;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIcon extends EditRecord
{
    use EditRedirectIndexTrait;

    protected static string $resource = IconResource::class;

    //    protected static string $view = 'filament.resources.system.category-icon-resource.pages.edit-record';

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
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['tip'] = Locale::trans('label.icon.desc', [], null);

        return $data;
    }

    protected function getViewData(): array
    {
        return [
            'desc' => Locale::trans('label.icon.desc', [], null),
        ];
    }

    public function afterSave(): void
    {
        Cache::clearIcon();
    }
}
