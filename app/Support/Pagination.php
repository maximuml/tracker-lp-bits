<?php

namespace App\Support;

/**
 * Stateless pagination-HTML renderer extracted from
 * `include/functions.php` (Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`").
 *
 * The legacy procedural helper
 *
 *   - `pager($rpp, $count, $href, $opts, $pagename)`
 *
 * collapses into the static method below. The legacy function now
 * proxies here.
 *
 * Lives under `App\Support` (not `App\Services`) because the method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Format}, {@see Html}, {@see Token}, {@see Strings}.
 *
 * Every method's contract is pinned by a unit test in
 * `tests/Unit/Support/PaginationTest.php`.
 */
final class Pagination
{
    /**
     * Build the legacy paginator HTML and SQL fragment.
     *
     * Returns an indexed array with the same shape as the legacy
     * `pager()`:
     *
     *   [0] => pagertop    (HTML string — prev/next + page links, top variant)
     *   [1] => pagerbottom (HTML string — page links + prev/next, bottom variant)
     *   [2] => limit clause ("limit N offset M")
     *   [3] => start offset (int)
     *   [4] => rows per page (int)
     *   [5] => current page (int)
     *
     * The method is intentionally **not** responsible for setting the
     * global `$add_key_shortcut` — the legacy proxy still does that
     * by calling `key_shortcut()` on the result.
     *
     * @param  int  $rpp  Rows per page
     * @param  int  $count  Total row count
     * @param  string  $href  Base URL with trailing `&` or `?` for appending the page param
     * @param  int  $page  Current page (0-indexed)
     * @param  int  $pages  Total number of pages
     * @param  array<string, string>  $labels  Translated label strings:
     *                                         'prev', 'next', 'alt_prev_title', 'alt_next_title',
     *                                         'shift_prev_title', 'shift_next_title'
     * @param  string  $pagename  Query-param name for the page number
     * @param  bool  $isPresto  Whether the user-agent is Opera/Presto
     * @return array{0: string, 1: string, 2: string, 3: int, 4: int, 5: int}
     */
    public static function render(
        int $rpp,
        int $count,
        string $href,
        int $page,
        int $pages,
        array $labels,
        string $pagename = 'page',
        bool $isPresto = false,
    ): array {
        $mp = $pages - 1;

        $prevTitle = $isPresto
            ? ($labels['shift_prev_title'] ?? '')
            : ($labels['alt_prev_title'] ?? '');
        $nextTitle = $isPresto
            ? ($labels['shift_next_title'] ?? '')
            : ($labels['alt_next_title'] ?? '');

        $prevLabel = '<b title="'.htmlspecialchars($prevTitle).'">&lt;&lt;&nbsp;'.($labels['prev'] ?? '').'</b>';
        $nextLabel = '<b title="'.htmlspecialchars($nextTitle).'">'.($labels['next'] ?? '').'&nbsp;&gt;&gt;</b>';

        // Build prev link
        $pager = '';
        if ($page >= 1) {
            $pager .= '<a href="'.htmlspecialchars($href.$pagename.'='.($page - 1)).'">';
            $pager .= $prevLabel;
            $pager .= '</a>';
        } else {
            $pager .= '<font class="gray">'.$prevLabel.'</font>';
        }

        $pager .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';

        // Build next link
        if ($page < $mp && $mp >= 0) {
            $pager .= '<a href="'.htmlspecialchars($href.$pagename.'='.($page + 1)).'">';
            $pager .= $nextLabel;
            $pager .= '</a>';
        } else {
            $pager .= '<font class="gray">'.$nextLabel.'</font>';
        }

        // Build page-number links
        if ($count) {
            $pagerarr = [];
            $dotted = 0;
            $dotspace = 3;
            $dotend = $pages - $dotspace;
            $curdotend = $page - $dotspace;
            $curdotstart = $page + $dotspace;

            for ($i = 0; $i < $pages; $i++) {
                if (($i >= $dotspace && $i <= $curdotend) || ($i >= $curdotstart && $i < $dotend)) {
                    if (! $dotted) {
                        $pagerarr[] = '...';
                    }
                    $dotted = 1;

                    continue;
                }
                $dotted = 0;
                $start = $i * $rpp + 1;
                $end = $start + $rpp - 1;
                if ($end > $count) {
                    $end = $count;
                }
                $text = $start.'&nbsp;-&nbsp;'.$end;
                if ($i != $page) {
                    $pagerarr[] = '<a href="'.htmlspecialchars($href.$pagename.'='.$i).'"><b>'.$text.'</b></a>';
                } else {
                    $pagerarr[] = '<font class="gray"><b>'.$text.'</b></font>';
                }
            }
            $pagerstr = implode(' | ', $pagerarr);
            $pagertop = "<p align=\"center\" class='nexus-pagination'>".$pager.'<br />'.$pagerstr."</p>\n";
            $pagerbottom = "<p align=\"center\" class='nexus-pagination'>".$pagerstr.'<br />'.$pager."</p>\n";
        } else {
            $pagertop = "<p align=\"center\" class='nexus-pagination'>".$pager."</p>\n";
            $pagerbottom = $pagertop;
        }

        $startOffset = $page * $rpp;

        return [$pagertop, $pagerbottom, "limit $rpp offset $startOffset", $startOffset, $rpp, $page];
    }

    /**
     * Resolve the current page from a raw request value.
     *
     * The legacy `pager()` computed `$page` from `$_GET[$pagename]`
     * with fallback to either `0` or `lastpagedefault` depending on
     * `$opts['lastpagedefault']`. This helper encapsulates that
     * resolution so the proxy can pre-compute and pass the clean
     * `$page` integer to {@see render()}.
     *
     * @param  int|string|null  $raw  Raw `$_GET[$pagename]` value
     * @param  int  $count  Total row count
     * @param  int  $rpp  Rows per page
     * @param  bool  $lastPageDefault  Whether to default to the last page
     */
    public static function resolvePage(
        int|string|null $raw,
        int $count,
        int $rpp,
        bool $lastPageDefault = false,
    ): int {
        $pageDefault = 0;
        if ($lastPageDefault) {
            $pageDefault = (int) floor(($count - 1) / $rpp);
            if ($pageDefault < 0) {
                $pageDefault = 0;
            }
        }

        if ($raw === null) {
            return $pageDefault;
        }

        $page = (int) $raw;

        return $page < 0 ? $pageDefault : $page;
    }
}
