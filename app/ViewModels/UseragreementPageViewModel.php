<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the useragreement page (useragreement/index.blade.php).
 *
 * Assembled by RulesController::userAgreement().
 */
final class UseragreementPageViewModel extends ViewModel
{
    public function __construct(
        public readonly mixed $SITENAME = null,
        public readonly mixed $BASEURL = null,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['SITENAME', 'BASEURL'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
