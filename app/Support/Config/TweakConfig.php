<?php

declare(strict_types=1);

namespace App\Support\Config;

final class TweakConfig extends Config
{
    public function enableTooltip(bool $default = false): bool
    {
        return $this->bool('enabletooltip', $default);
    }

    public function titleKeywords(string $default = ''): string
    {
        return $this->string('titlekeywords', $default);
    }

    public function metaKeywords(string $default = ''): string
    {
        return $this->string('metakeywords', $default);
    }

    public function metaDescription(string $default = ''): string
    {
        return $this->string('metadescription', $default);
    }

    public function cssDate(string $default = ''): string
    {
        return $this->string('cssdate', $default);
    }

    public function dateFounded(string $default = ''): string
    {
        return $this->string('datefounded', $default);
    }

    public function enableSqlDebug(bool $default = false): bool
    {
        return $this->bool('enablesqldebug', $default);
    }

    public function sqlDebug(int $default = 0): int
    {
        return $this->int('sqldebug', $default);
    }

    public function analyticsCode(string $default = ''): string
    {
        return $this->string('analyticscode', $default);
    }

    public function where(string $default = ''): string
    {
        return $this->string('where', $default);
    }

    public function iplog1(int $default = 0): int
    {
        return $this->int('iplog1', $default);
    }

    public function bonus(string $default = ''): string
    {
        return $this->string('bonus', $default);
    }

    public function enableLocation(bool $default = false): bool
    {
        return $this->bool('enablelocation', $default);
    }
}
