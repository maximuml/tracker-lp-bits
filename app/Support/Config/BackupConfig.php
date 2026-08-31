<?php

declare(strict_types=1);

namespace App\Support\Config;

final class BackupConfig extends Config
{
    public function exportPath(string $default = ''): string
    {
        return $this->string('export_path', $default);
    }

    public function retentionCount(int $default = 0): int
    {
        return $this->int('retention_count', $default);
    }

    /**
     * GPG recipient (email or key ID) for encrypting backup archives.
     * When empty, backups are stored unencrypted (not recommended for production).
     */
    public function gpgRecipient(): string
    {
        return (string) config('backup.gpg_recipient', '');
    }
}
