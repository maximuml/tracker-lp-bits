<?php

declare(strict_types=1);

namespace App\ViewModels\Usercp;

/**
 * HMAC-signed passkey login form for the usercp home section.
 *
 * The action URL embeds the configured login secret as the path —
 * the legacy passkeyLogin controller verifies passkey + timestamp +
 * signature without exposing the secret to the client beyond the URL.
 */
final readonly class PasskeyLoginForm
{
    public function __construct(
        public string $action,
        public string $passkey,
        public int $timestamp,
        public string $signature,
    ) {}
}
