<?php

declare(strict_types=1);

namespace App\ViewModels\Bonus;

/**
 * The submit-button state of one bonus shop row — the label and whether
 * the exchange is allowed. Replaces the `<td><input type="submit">`
 * strings `BonusPageService::renderTradeButton()` used to return.
 */
final readonly class BonusTradeButton
{
    public function __construct(
        public string $label,
        public bool $disabled,
    ) {}
}
