<?php

declare(strict_types=1);

namespace Nexus\Attachment\Drivers;

use App\Support\Config\SiteConfig;
use App\Support\Url;
use Nexus\Attachment\Storage;

class Local extends Storage
{
    public function upload(string $filepath): string
    {
        throw new \RuntimeException('Not implemented');
    }

    public function getBaseUrl(): string
    {
        return sprintf('%s/%s', Url::schemeAndHost(false), trim(SiteConfig::current()->attachment->httpDirectory(), '/'));
    }

    public function getDriverName(): string
    {
        return static::DRIVER_LOCAL;
    }
}
