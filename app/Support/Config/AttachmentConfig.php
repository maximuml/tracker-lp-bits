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

    public function isEnabled(): bool
    {
        return $this->enableAttach(false);
    }

    public function classThreshold(int $tier): int
    {
        return $this->int("class{$this->tierName($tier)}", 0);
    }

    public function countLimit(int $tier): int
    {
        return $this->int("count{$this->tierName($tier)}", 0);
    }

    public function sizeLimit(int $tier): int
    {
        return $this->int("size{$this->tierName($tier)}", 0);
    }

    public function extensions(int $tier): string
    {
        return $this->string("ext{$this->tierName($tier)}", '');
    }

    private function tierName(int $tier): string
    {
        return match ($tier) {
            1 => 'one',
            2 => 'two',
            3 => 'three',
            4 => 'four',
            default => '',
        };
    }
}
