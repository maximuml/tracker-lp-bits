<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the donate page (donate/index.blade.php).
 *
 * Assembled by InfoController::donate() from InfoRepository::donationPageData() + the `thanks` query flag.
 */
final class DonatePageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $crypto
     */
    public function __construct(
        public readonly bool $enabled = false,
        public readonly string $custom = '',
        public readonly string $paypal = '',
        public readonly bool $showPaypal = false,
        public readonly array $crypto = [],
        public readonly bool $showCustom = false,
        public readonly bool $showAny = false,
        public readonly int $accountantId = 0,
        public readonly string $baseUrl = '',
        public readonly bool $thanks = false,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['enabled', 'custom', 'paypal', 'showPaypal', 'crypto', 'showCustom', 'showAny', 'accountantId', 'baseUrl', 'thanks'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
