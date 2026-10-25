<?php

declare(strict_types=1);

namespace App\Services\Captcha;

use Illuminate\Container\Attributes\Scoped;

/**
 * Per-request render state for the Turnstile driver — the api.js script
 * must be injected once per request regardless of how many driver
 * instances are constructed. Scoped-flushed between jobs/worker cycles,
 * replacing the former class static.
 */
#[Scoped]
final class TurnstileRenderState
{
    public bool $scriptInjected = false;
}
