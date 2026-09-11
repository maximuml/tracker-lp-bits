<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\OverForumResource\Pages;

use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Filament\Resources\Section\OverForumResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOverForum extends EditRecord
{
    protected static string $resource = OverForumResource::class;

    /** @return array<DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn ($record) => app(ForumRepositoryInterface::class)->deleteOverforum($record->id)),
        ];
    }
}
