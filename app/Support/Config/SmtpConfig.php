<?php

declare(strict_types=1);

namespace App\Support\Config;

final class SmtpConfig extends Config
{
    public function type(string $default = 'none'): string
    {
        return $this->string('smtptype', $default);
    }

    public function emailNotify(bool $default = false): bool
    {
        return $this->bool('emailnotify', $default);
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

    public function address(string $default = ''): string
    {
        return $this->string('smtpaddress', $default);
    }

    public function accountName(string $default = ''): string
    {
        return $this->string('accountname', $default);
    }

    public function accountPassword(string $default = ''): string
    {
        return $this->string('accountpassword', $default);
    }

    public function encryption(): ?string
    {
        $value = $this->data['encryption'] ?? null;
        return $value !== null ? (string) $value : null;
    }

    public function smtpName(string $default = ''): string
    {
        return $this->string('smtpname', $default);
    }

}
