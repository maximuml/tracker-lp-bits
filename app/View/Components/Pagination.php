<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Accessible numbered pagination for migrated views.
 *
 * The legacy `App\Support\Pagination::render()` produces the historical
 * table-based pager markup; this component renders a semantic `<nav>`
 * with `aria-current` for new/migrated templates instead.
 *
 * `$href` is a URL template containing the literal `{page}` placeholder,
 * which is replaced with the 1-based page number:
 *
 *     <x-pagination :page="$page" :pages="$pages" href="/torrents?cat=1&page={page}" />
 */
final class Pagination extends Component
{
    /** How many page links to show on each side of the current page. */
    private const WINDOW = 2;

    /**
     * Ordered page items: ints for pages, '…' markers for gaps.
     *
     * @var list<int|string>
     */
    public readonly array $items;

    public function __construct(
        public readonly int $page,
        public readonly int $pages,
        public readonly string $href,
        public readonly string $label = 'Pagination',
        public readonly string $prevLabel = 'Previous',
        public readonly string $nextLabel = 'Next',
    ) {
        $this->items = self::window($this->page, $this->pages);
    }

    /**
     * Compute the page window: always first, last, current ± WINDOW,
     * with '…' markers for gaps.
     *
     * @return list<int|string>
     */
    public static function window(int $page, int $pages): array
    {
        if ($pages <= 0) {
            return [];
        }

        $page = max(1, min($page, $pages));
        $wanted = [1, $pages];
        for ($i = $page - self::WINDOW; $i <= $page + self::WINDOW; $i++) {
            $wanted[] = $i;
        }
        $wanted = array_values(array_unique(array_filter(
            $wanted,
            static fn (int $i): bool => $i >= 1 && $i <= $pages,
        )));
        sort($wanted);

        $items = [];
        $prev = 0;
        foreach ($wanted as $p) {
            if ($prev !== 0 && $p - $prev > 1) {
                $items[] = '…';
            }
            $items[] = $p;
            $prev = $p;
        }

        return $items;
    }

    /**
     * Build the URL for a page number from the `{page}` template.
     */
    public function url(int $page): string
    {
        return str_replace('{page}', (string) $page, $this->href);
    }

    public function render(): View
    {
        return view('components.pagination');
    }
}
