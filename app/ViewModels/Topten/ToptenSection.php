<?php

declare(strict_types=1);

namespace App\ViewModels\Topten;

use App\Support\Html\SafeHtml;

/**
 * One leaderboard section of the top-ten page: which component renders it,
 * the already-formatted rows, the caption HTML and the limit-switch links.
 */
final class ToptenSection
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<int>  $limits
     */
    public function __construct(
        public readonly string $view,
        public readonly array $rows,
        public readonly string $caption,
        public readonly array $limits,
        public readonly string $subtype,
        public readonly string $what,
    ) {}

    /**
     * @param  array<string, mixed>  $section
     */
    public static function fromArray(array $section): self
    {
        return new self(
            view: (string) $section['view'],
            rows: $section['data'] ?? [],
            caption: (string) ($section['caption'] ?? ''),
            limits: $section['limits'] ?? [],
            subtype: (string) ($section['subtype'] ?? ''),
            what: (string) ($section['what'] ?? ''),
        );
    }

    /**
     * Caption with the limit-switch links (Top 100 / Top 250) appended.
     */
    public function captionHtml(int $type): SafeHtml
    {
        $links = view('components.topten.limit-links', [
            'type' => $type,
            'subtype' => $this->subtype,
            'limits' => $this->limits,
        ])->render();

        return SafeHtml::fromUntrustedHtml($this->caption.$links);
    }
}
