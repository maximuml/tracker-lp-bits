<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the aboutnexus page (aboutnexus/index.blade.php).
 *
 * Wraps InfoRepository::aboutNexus() output.
 */
final class AboutnexusPageViewModel extends ViewModel
{
    public function __construct(
        public readonly mixed $captions = null,
        public readonly mixed $languages = null,
        public readonly mixed $notes = null,
        public readonly mixed $stylesheets = null,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['captions', 'languages', 'notes', 'stylesheets'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
