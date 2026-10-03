<?php

declare(strict_types=1);

namespace App\Filament\Resources\Section\ForumResource\Pages;

use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Filament\Resources\Section\ForumResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditForum extends EditRecord
{
    protected static string $resource = ForumResource::class;

    /** @return array<DeleteAction> */
    private ForumRepositoryInterface $forumRepository;

    public function boot(
        ForumRepositoryInterface $forumRepository,
    ): void {
        $this->forumRepository = $forumRepository;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn ($record) => $this->forumRepository->deleteForum($record->id)),
        ];
    }
}
