<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\UserClass as UserClassEnum;
use App\Filament\Widgets\RefererSources\RefererTrend;
use App\Filament\Widgets\RefererSources\TopReferers;
use App\Models\User;
use Filament\Pages\Dashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class RefererSources extends Dashboard
{
    protected Width|string|null $maxContentWidth = 'full';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string $routePath = 'referer-sources';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 5;

    public function getTitle(): string|Htmlable
    {
        return self::getNavigationLabel();
    }

    public static function getNavigationLabel(): string
    {
        return __('referer-sources.label');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->class >= UserClassEnum::SYSOP->value;
    }

    /**
     * @return array<int, string>
     */
    public function getWidgets(): array
    {
        return [
            RefererTrend::class,
            TopReferers::class,
        ];
    }
}
