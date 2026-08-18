<?php

namespace App\Filament\Resources\System\AgentAllowResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\System\AgentAllowResource;
use Filament\Resources\Pages\EditRecord;

class EditAgentAllow extends EditRecord
{
    protected static string $resource = AgentAllowResource::class;

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->using(function ($record) {
                $record->delete();
                \App\Support\Cache::clearAgentAllowDeny();
                return redirect(AgentAllowResource::getUrl());
            })
        ];
    }

    public function afterSave(): void
    {
        \App\Support\Cache::clearAgentAllowDeny();
    }
}
