<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * ViewModel for the staffmess page (staffmess/index.blade.php).
 *
 * Assembled by StaffMessageController::staffmess(); dead key stdheadMsgalert dropped.
 */
final class StaffmessPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<int, mixed>>  $classes
     */
    public function __construct(
        public readonly array $classes,
        public readonly SafeHtml $body,
        public readonly int $receiver,
        public readonly SafeHtml $username,
        public readonly int $sent,
        public readonly bool $showReturnto,
        public readonly string $returnto,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['classes', 'body', 'receiver', 'username', 'sent', 'showReturnto', 'returnto'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
