<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the clearcache page (clearcache/index.blade.php).
 *
 * Assembled by AdminToolsController::clearcache() and clearCacheSubmit().
 */
final class ClearcachePageViewModel extends ViewModel
{
    public function __construct(
        public readonly bool $done,
        public readonly string $error,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
