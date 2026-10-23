<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet on credential-field reads outside the authentication boundary.
 *
 * `passhash`, `secret`, `auth_version`, and `auth_key` authenticate the
 * user; reading them off the wrong model instance silently breaks auth —
 * this actually happened when `UsercpSecurityCommand` read `passhash` off
 * `ActorContext::$user`, which is rehydrated from `toLegacyArray()` and
 * deliberately omits credential columns (null hash → every password check
 * failed).
 *
 * Reads are only permitted inside the pinned boundary files, which load
 * full database models (login, cookie verification, password change,
 * passkey download). Everywhere else must go through those services —
 * or fetch a fresh model — never trust a hydrated/partial one.
 *
 * Allowlist rationale:
 *   - Auth/NexusWebUserProvider — password verification for the guard.
 *   - Support/AuthCookie — cookie payload auth_version checks.
 *   - Http/Controllers/AuthenticateController — 2FA `secret` at login.
 *   - Repositories/TorrentDownloadRepository — passkey `secret` HMAC
 *     for download links.
 *   - Repositories/UsercpSecurityCommand — password/challenge verify on
 *     a fresh findOrFail() model (the fixed regression site).
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class CredentialFieldAccessRatchetTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /** @var list<string> */
    private const FIELDS = ['passhash', 'secret', 'auth_version', 'auth_key'];

    /**
     * Audited readers: relative path → field → pinned attribute-read count.
     *
     * @var array<string, array<string, int>>
     */
    private const ALLOWED = [
        'Auth/NexusWebUserProvider.php' => ['passhash' => 1],
        'Support/AuthCookie.php' => ['auth_version' => 1],
        'Http/Controllers/AuthenticateController.php' => ['secret' => 1],
        'Repositories/TorrentDownloadRepository.php' => ['secret' => 1],
        'Repositories/UsercpSecurityCommand.php' => ['passhash' => 2],
    ];

    public function test_credential_fields_are_only_read_at_the_auth_boundary(): void
    {
        $violations = [];
        $allowlistCounts = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::APP_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace(self::APP_DIR.'/', '', $file->getPathname());
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            foreach (explode("\n", $content) as $lineNumber => $line) {
                $trimmed = ltrim($line);
                if (str_starts_with($trimmed, '*')
                    || str_starts_with($trimmed, '//')
                    || str_starts_with($trimmed, '/*')
                    || str_starts_with($trimmed, '#')
                ) {
                    continue;
                }

                $code = (string) preg_replace(
                    ['/"(?:[^"\\\\]|\\\\.)*"/', "/'(?:[^'\\\\]|\\\\.)*'/"],
                    "''",
                    $line,
                );

                foreach (self::FIELDS as $field) {
                    if (! preg_match('/->\s*'.$field.'\b/', $code)) {
                        continue;
                    }

                    $allowed = self::ALLOWED[$relativePath][$field] ?? 0;
                    if ($allowed > 0) {
                        $allowlistCounts[$relativePath][$field]
                            = ($allowlistCounts[$relativePath][$field] ?? 0) + 1;

                        continue;
                    }

                    $violations[] = "{$relativePath}:".($lineNumber + 1)." reads ->{$field}";
                }
            }
        }

        foreach (self::ALLOWED as $path => $fields) {
            foreach ($fields as $field => $expected) {
                $actual = $allowlistCounts[$path][$field] ?? 0;
                if ($actual !== $expected) {
                    $violations[] = "{$path} has {$actual} ->{$field} reads, expected {$expected} — update the pin or justify the change";
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Credential-field reads outside the auth boundary:\n".implode("\n", $violations)
            ."\n\npasshash/secret/auth_version/auth_key must only be read on full DB "
            .'models at the authentication boundary — ActorContext::$user omits '
            .'them, so any read off a hydrated model silently returns null. '
            .'Justify boundary reads in the class docblock and pin them in self::ALLOWED.',
        );
    }
}
