<?php

declare(strict_types=1);

namespace App\Support\Config;

final class MeiliSearchConfig extends Config
{
    public function enabled(bool $default = false): bool
    {
        return $this->bool('enabled', $default);
    }

    public function searchDescription(bool $default = false): bool
    {
        return $this->bool('search_description', $default);
    }

    public function defaultSearchMode(string $default = 'and'): string
    {
        return $this->string('default_search_mode', $default);
    }
}
