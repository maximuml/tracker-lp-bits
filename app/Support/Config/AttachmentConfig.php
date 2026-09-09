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

    public function saveDirectory(string $default = ''): string
    {
        return $this->string('savedirectory', $default);
    }

    public function saveDirectoryType(string $default = 'monthdir'): string
    {
        return $this->string('savedirectorytype', $default);
    }

    public function thumbnailType(string $default = 'createthumb'): string
    {
        return $this->string('thumbnailtype', $default);
    }

    public function thumbWidth(int $default = 200): int
    {
        return $this->int('thumbwidth', $default);
    }

    public function thumbHeight(int $default = 200): int
    {
        return $this->int('thumbheight', $default);
    }

    public function thumbQuality(int $default = 80): int
    {
        return $this->int('thumbquality', $default);
    }

    public function watermarkPos(string $default = 'no'): string
    {
        return $this->string('watermarkpos', $default);
    }

    public function watermarkWidth(int $default = 100): int
    {
        return $this->int('watermarkwidth', $default);
    }

    public function watermarkHeight(int $default = 100): int
    {
        return $this->int('watermarkheight', $default);
    }

    public function watermarkQuality(int $default = 90): int
    {
        return $this->int('watermarkquality', $default);
    }

    public function altThumbWidth(int $default = 100): int
    {
        return $this->int('altthumbwidth', $default);
    }

    public function altThumbHeight(int $default = 100): int
    {
        return $this->int('altthumbheight', $default);
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
