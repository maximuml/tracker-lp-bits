<?php

declare(strict_types=1);

namespace App\Filament\Resources\User\ExamUserResource\Pages;

use App\Filament\PageList;
use App\Filament\PreloadsUserDisplayTrait;
use App\Filament\Resources\User\ExamUserResource;
use Filament\Pages\Actions;

class ListExamUsers extends PageList
{
    use PreloadsUserDisplayTrait;

    protected static string $resource = ExamUserResource::class;

    /** @return list<string> */
    protected function userDisplayKeys(): array
    {
        return ['uid'];
    }

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
