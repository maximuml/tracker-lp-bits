<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\ExamResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\ExamResource;
use Filament\Actions\CreateAction;

class ListExams extends PageList
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
