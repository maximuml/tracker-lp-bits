<?php

namespace App\Filament\Resources\Section\SecondIconResource\Pages;

use App\Filament\Resources\Section\SecondIconResource;
use App\Models\SecondIcon;
use Filament\Resources\Pages\CreateRecord;

class CreateSecondIcon extends CreateRecord
{
    protected static string $resource = SecondIconResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SecondIcon::formatFormData($data);
    }
}
