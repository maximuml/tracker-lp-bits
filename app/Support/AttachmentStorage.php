<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Config\SiteConfig;
use InvalidArgumentException;

/**
 * Image-hosting driver manager extracted from `Nexus\Attachment\Storage`.
 *
 * Resolves the configured image-hosting driver (local, Chevereto, Lsky)
 * and proxies `upload()` / `getImageUrl()` calls to the underlying
 * driver implementation. Drivers are cached per name for the request.
 */
final class AttachmentStorage
{
    /** @var array<string, AttachmentDriver> */
    private static array $drivers = [];

    public const DRIVER_LOCAL = 'local';

    public const DRIVER_CHEVERETO = 'chevereto';

    public const DRIVER_LSKY = 'lsky';

    /**
     * Resolve the configured driver (or the one named explicitly).
     */
    public static function driver(?string $name = null): AttachmentDriver
    {
        $name = $name ?: SiteConfig::current()->imageHosting->driver();

        return self::$drivers[$name] ??= self::build($name);
    }

    private static function build(string $name): AttachmentDriver
    {
        return match ($name) {
            self::DRIVER_LOCAL => new Drivers\LocalAttachmentDriver,
            self::DRIVER_CHEVERETO => new Drivers\CheveretoAttachmentDriver,
            self::DRIVER_LSKY => new Drivers\LskyAttachmentDriver,
            default => throw new InvalidArgumentException("Unsupported attachment driver: {$name}"),
        };
    }
}
