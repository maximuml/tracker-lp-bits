<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the testip page (testip/index.blade.php).
 *
 * Assembled by AdminToolsController::testip().
 */
final class TestipPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $banRows
     */
    public function __construct(
        public readonly string $ip,
        public readonly bool $isBanned,
        public readonly array $banRows,
        public readonly bool $hasResult,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
