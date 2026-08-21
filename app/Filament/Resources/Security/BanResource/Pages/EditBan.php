<?php

namespace App\Filament\Resources\Security\BanResource\Pages;

use App\Filament\Resources\Security\BanResource;
use App\Support\Cache;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBan extends EditRecord
{
    protected static string $resource = BanResource::class;

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function afterSave(): void
    {
        Cache::clearAgentAllowDeny();
    }
}
