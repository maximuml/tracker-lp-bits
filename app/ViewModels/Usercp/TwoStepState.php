<?php

declare(strict_types=1);

namespace App\ViewModels\Usercp;

/**
 * Two-step authentication state for the security section.
 *
 * `secret`/`qrCodeUrl` are only populated when the user has no secret
 * yet (`hasSecret === false`) — i.e. the bind-a-new-device flow.
 */
final readonly class TwoStepState
{
    public function __construct(
        public bool $hasSecret,
        public string $secret,
        public string $qrCodeUrl,
    ) {}
}
