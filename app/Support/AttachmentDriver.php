<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Contract for image-hosting drivers extracted from `Nexus\Attachment\Storage`.
 *
 * Each driver uploads a local file to its remote store and returns the
 * public URL. `getImageUrl()` resolves a stored location (relative path
 * or fragment) to a fully-qualified URL.
 */
interface AttachmentDriver
{
    /**
     * Upload a local file and return its full public URL.
     */
    public function upload(string $filepath): string;

    /**
     * Return the base URL prefix for stored resources.
     */
    public function getBaseUrl(): string;

    /**
     * Return the driver's canonical name (matches an `AttachmentStorage::DRIVER_*` constant).
     */
    public function getDriverName(): string;

    /**
     * Upload a temporary upload file, deriving its extension from the
     * original name when missing, then return the location relative to
     * the driver's base URL.
     */
    public function uploadGetLocation(string $filepath, string $originalName): string;

    /**
     * Resolve a stored location (or absolute URL) to a public URL.
     */
    public function getImageUrl(string $location): string;
}
