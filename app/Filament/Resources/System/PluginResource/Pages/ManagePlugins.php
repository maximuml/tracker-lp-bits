<?php

namespace App\Filament\Resources\System\PluginResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\System\PluginResource;
use Filament\Actions\CreateAction;

class ManagePlugins extends PageListSingle
{
    protected static string $resource = PluginResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
