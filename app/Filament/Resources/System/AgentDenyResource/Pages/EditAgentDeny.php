<?php

namespace App\Filament\Resources\System\AgentDenyResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\System\AgentDenyResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgentDeny extends EditRecord
{
    protected static string $resource = AgentDenyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->using(function ($record) {
                $record->delete();
                \App\Support\Cache::clearAgentAllowDeny();
                return redirect(AgentDenyResource::getUrl());
            })
        ];
    }

    public function afterSave()
    {
        \App\Support\Cache::clearAgentAllowDeny();
    }
}
