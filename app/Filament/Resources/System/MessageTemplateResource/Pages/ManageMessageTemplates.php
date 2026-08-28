<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\MessageTemplateResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\System\MessageTemplateResource;
use Filament\Actions\CreateAction;

class ManageMessageTemplates extends PageListSingle
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
