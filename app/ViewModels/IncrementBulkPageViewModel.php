<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the increment-bulk page (increment-bulk/index.blade.php).
 *
 * Assembled by SystemBulkController::incrementBulk(); dead key stdheadMsgalert dropped.
 */
final class IncrementBulkPageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $validTypeMap
     * @param  array<int, array<int, mixed>>  $classes
     * @param  array<string, mixed>  $CURUSER
     */
    public function __construct(
        public readonly array $validTypeMap,
        public readonly string $type,
        public readonly array $classes,
        public readonly string $receiver,
        public readonly string $body,
        public readonly string|int|null $sent,
        public readonly string $returnto,
        public readonly string $httpReferer,
        public readonly string $submitLabel,
        public readonly array $CURUSER,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['validTypeMap', 'type', 'classes', 'receiver', 'body', 'sent', 'returnto', 'httpReferer', 'submitLabel', 'CURUSER'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
