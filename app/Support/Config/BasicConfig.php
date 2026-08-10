<?php

declare(strict_types=1);

namespace App\Support\Config;

final class BasicConfig extends Config
{
    public function baseUrl(string $default = ''): string
    {
        return $this->string('BASEURL', $default);
    }

    public function siteName(string $default = ''): string
    {
        return $this->string('SITENAME', $default);
    }

    public function announceUrl(string $default = ''): string
    {
        return $this->string('announce_url', $default);
    }

}
