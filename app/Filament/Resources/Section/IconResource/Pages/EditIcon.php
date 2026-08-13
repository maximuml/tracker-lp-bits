<?php

namespace App\Filament\Resources\Section\IconResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\EditRedirectIndexTrait;
use App\Filament\Resources\Section\IconResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIcon extends EditRecord
{
    use EditRedirectIndexTrait;

    protected static string $resource = IconResource::class;

//    protected static string $view = 'filament.resources.system.category-icon-resource.pages.edit-record';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['tip'] = \App\Support\Locale::trans('label.icon.desc', [], null);
        return $data;
    }

    protected function getViewData(): array
    {
        return [
            'desc' => \App\Support\Locale::trans('label.icon.desc', [], null)
        ];
    }

    public function afterSave()
    {
        \App\Support\Cache::clearIcon();
    }
}
