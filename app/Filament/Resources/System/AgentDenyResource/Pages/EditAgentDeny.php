<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\AgentDenyResource\Pages;

use App\Filament\Resources\System\AgentDenyResource;
use App\Support\Cache;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAgentDeny extends EditRecord
{
    protected static string $resource = AgentDenyResource::class;

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->using(function ($record) {
                $record->delete();
                Cache::clearAgentAllowDeny();

                return redirect(AgentDenyResource::getUrl());
            }),
        ];
    }

    public function afterSave(): void
    {
        Cache::clearAgentAllowDeny();
    }
}
