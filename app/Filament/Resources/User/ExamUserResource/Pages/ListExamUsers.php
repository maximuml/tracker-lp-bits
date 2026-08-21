<?php

namespace App\Filament\Resources\User\ExamUserResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\User\ExamUserResource;
use Filament\Pages\Actions;

class ListExamUsers extends PageList
{
    protected static string $resource = ExamUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //            Actions\CreateAction::make(),
        ];
    }
}
