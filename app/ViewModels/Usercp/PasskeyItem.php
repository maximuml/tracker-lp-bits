<?php

declare(strict_types=1);

namespace App\ViewModels\Usercp;

/**
 * One registered WebAuthn passkey for the security section list.
 *
 * `createdAt` is raw — the template renders it through `<x-time>`.
 * `showCredentialId` distinguishes the known-authenticator case
 * ("<b>name</b> (credential_id)") from the fallback
 * ("<b>credential_id</b>" alone).
 */
final readonly class PasskeyItem
{
    public function __construct(
        public string $credentialId,
        public string $iconUrl,
        public string $iconAlt,
        public string $displayName,
        public bool $showCredentialId,
        public mixed $createdAt,
    ) {}
}
