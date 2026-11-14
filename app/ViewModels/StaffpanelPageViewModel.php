<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the staff panel page (staffpanel/index.blade.php).
 *
 * Assembled by StaffPageController::staffpanel(); each panel group is
 * rendered through the `staffpanel._group` include.
 */
final class StaffpanelPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $sysopPanels
     * @param  array<int, array<string, mixed>>  $adminPanels
     * @param  array<int, array<string, mixed>>  $modPanels
     */
    public function __construct(
        public readonly array $sysopPanels,
        public readonly array $adminPanels,
        public readonly array $modPanels,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
