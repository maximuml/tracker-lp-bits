<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * Base readonly ViewModel for page data.
 *
 * PageServices return these instead of plain arrays. Controllers
 * pass them to Blade views via ->toArray(). The readonly property
 * ensures immutability after construction.
 */
abstract class ViewModel
{
    /**
     * Convert to array for Blade view consumption.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
