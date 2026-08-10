<?php

declare(strict_types=1);

namespace App\Support\Config;

final class AttachmentConfig extends Config
{
    public function httpDirectory(string $default = ''): string
    {
        return $this->string('httpdirectory', $default);
    }

    public function enableAttach(bool $default = false): bool
    {
        return $this->bool('enableattach', $default);
    }

}
