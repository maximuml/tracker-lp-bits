<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\ViewModels\Topten\ToptenSection;

/**
 * ViewModel for the top-ten page.
 *
 * Assembled by ToptenController from ToptenRepository::page() output.
 */
final class ToptenPageViewModel extends ViewModel
{
    /**
     * @param  list<ToptenSection>  $sections
     * @param  list<array{id: string, label: string, url: string}>  $tabs
     */
    public function __construct(
        public readonly int $type,
        public readonly int $limit,
        public readonly ?string $subtype,
        public readonly bool $enabledDonation,
        public readonly string $dateFounded,
        public readonly string $generatedAt,
        public readonly array $sections,
        public readonly array $tabs,
    ) {}

    /**
     * @param  array<string, mixed>  $page  Page data from ToptenRepository::page()
     * @param  list<array{id: string, label: string, url: string}>  $tabs
     */
    public static function fromPage(array $page, array $tabs): self
    {
        $sections = array_map(
            fn (array $section): ToptenSection => ToptenSection::fromArray($section),
            $page['sections'] ?? [],
        );

        return new self(
            type: (int) $page['type'],
            limit: (int) $page['limit'],
            subtype: is_string($page['subtype'] ?? null) ? $page['subtype'] : null,
            enabledDonation: (bool) ($page['enabledDonation'] ?? false),
            dateFounded: (string) ($page['dateFounded'] ?? ''),
            generatedAt: (string) ($page['generatedAt'] ?? ''),
            sections: array_values($sections),
            tabs: $tabs,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'sections' => $this->sections,
            'toptenTabs' => $this->tabs,
            'generatedAt' => $this->generatedAt,
            'dateFounded' => $this->dateFounded,
        ];
    }
}
