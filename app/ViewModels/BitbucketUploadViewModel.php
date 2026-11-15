<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the bitbucket avatar-upload page (bitbucket/upload.blade.php).
 *
 * Assembled by BitbucketUploadController::create(). The blade forwards
 * everything except pageTitle to components.bitbucket.upload via @include.
 */
final class BitbucketUploadViewModel extends ViewModel
{
    public function __construct(
        public readonly string $pageTitle,
        public readonly int $maxFileSize,
        public readonly int $scaleHeight,
        public readonly int $scaleWidth,
        public readonly bool $bucketWritable,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
