<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Settings;
use Illuminate\Console\Command;

/**
 * Generate a new passkey login v2 signing key from random_bytes(32).
 *
 * The key is stored in the settings table as a 64-char hex string.
 * When rotating, the current key is moved to "previous" and the new
 * key becomes "current", allowing an overlap window for clients to
 * transition.
 */
final class GeneratePasskeySigningKey extends Command
{
    protected $signature = 'passkey:generate-key
                            {--rotate : Move current key to previous before generating a new one}
                            {--key-id= : Key ID to use (default: auto-generated)}';

    protected $description = 'Generate a passkey login v2 signing key from random_bytes(32)';

    public function handle(): int
    {
        $rotate = (bool) $this->option('rotate');
        $keyId = (string) $this->option('key-id');

        if ($keyId === '') {
            $keyId = 'key-'.bin2hex(random_bytes(4));
        }

        $newKey = bin2hex(random_bytes(32));

        if ($rotate) {
            $currentKey = (string) Settings::get('passkey_login_signing_key_current', '');
            $currentKeyId = (string) Settings::get('passkey_login_signing_key_id_current', '');

            if ($currentKey !== '' && $currentKeyId !== '') {
                Settings::saveBatch('security', [
                    'passkey_login_signing_key_previous' => $currentKey,
                    'passkey_login_signing_key_id_previous' => $currentKeyId,
                ]);
                $this->info("Moved current key ({$currentKeyId}) to previous.");
            }
        }

        Settings::saveBatch('security', [
            'passkey_login_signing_key_current' => $newKey,
            'passkey_login_signing_key_id_current' => $keyId,
            'passkey_login_v2_enabled' => 'yes',
        ]);

        $this->info("Generated new signing key with ID: {$keyId}");
        $this->warn('Store this key securely — it is required for passkey login v2.');
        $this->warn('Clients must use key_id="'.$keyId.'" when computing signatures.');

        return self::SUCCESS;
    }
}
