<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\SettingResource\Pages;

use App\Filament\Resources\System\SettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSetting extends CreateRecord
{
    protected static string $resource = SettingResource::class;
}
