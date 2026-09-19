<?php

declare(strict_types=1);

namespace App\ViewModels\Message;

/**
 * One `<option>` in a mailbox select (move-to or jump-to).
 */
final class MessageBoxOption
{
    public function __construct(
        public readonly int $value,
        public readonly string $label,
        public readonly bool $selected = false,
    ) {}
}
