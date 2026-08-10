<?php

declare(strict_types=1);

namespace App\Support\Config;

final class SmtpConfig extends Config
{
    public function type(string $default = 'none'): string
    {
        return $this->string('smtptype', $default);
    }

    public function smtp(string $default = ''): string
    {
        return $this->string('smtp', $default);
    }

    public function host(string $default = ''): string
    {
        return $this->string('smtp_host', $default);
    }

    public function port(string $default = ''): string
    {
        return $this->string('smtp_port', $default);
    }

    public function from(string $default = ''): string
    {
        return $this->string('smtp_from', $default);
    }

}
