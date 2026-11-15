<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the bitbucket upload-result page (bitbucket/result.blade.php).
 *
 * Assembled by BitbucketUploadController::store().
 */
final class BitbucketResultViewModel extends ViewModel
{
    /**
     * @param  list<array{url: string, width: int, height: int, newwidth: int, newheight: int}>  $results
     * @param  list<array{filename: string, message: string}>  $errors
     */
    public function __construct(
        public readonly array $results,
        public readonly array $errors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
