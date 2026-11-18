<?php

declare(strict_types=1);

namespace App\Support\Config;

final class ImageHostingConfig extends Config
{
    public function driver(string $default = 'local'): string
    {
        return $this->string('driver', $default);
    }
}
