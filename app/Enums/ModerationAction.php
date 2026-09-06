<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for user moderation increment/decrement actions.
 *
 * Mirrors the string values used by UserModerationRepository::incrementDecrement().
 */
enum ModerationAction: string
{
    case INCREMENT = 'Increment';
    case DECREMENT = 'Decrement';
}
