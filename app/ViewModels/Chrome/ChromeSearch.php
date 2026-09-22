<?php

declare(strict_types=1);

namespace App\ViewModels\Chrome;

use App\Support\Locale;
use App\Support\PageLayoutContext;
use App\Support\RequestContext;
use App\Support\Settings;

/**
 * Header global-search strip data (ADR 0018): keyword field, search-area
 * select options and form target. Rendered inside the userbar tools row.
 */
final class ChromeSearch
{
    /**
     * @param  list<array{value: int, label: string, selected: bool}>  $searchAreas
     */
    private function __construct(
        public readonly bool $globalSearchEnabled,
        public readonly string $requestSearch,
        public readonly string $searchFormTarget,
        public readonly string $searchKeywordPlaceholder,
        public readonly string $globalSearchLabel,
        public readonly array $searchAreas,
    ) {}

    public static function load(PageLayoutContext $context): self
    {
        $searchAreas = [];
        foreach ([0, 1, 3] as $area) {
            $searchAreas[] = [
                'value' => $area,
                'label' => Locale::trans("search.search_area_options.{$area}", [], null),
                'selected' => (int) $context->requestSearchArea === $area,
            ];
        }

        return new self(
            globalSearchEnabled: Settings::get('main.enable_global_search') === 'yes',
            requestSearch: $context->requestSearch,
            searchFormTarget: RequestContext::instance()->getScript() === 'search' ? '_self' : '_blank',
            searchKeywordPlaceholder: Locale::trans('search.search_keyword'),
            globalSearchLabel: Locale::trans('search.global_search'),
            searchAreas: $searchAreas,
        );
    }
}
