<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Repositories\IndexRepository;
use App\Support\AssetAppender;
use App\Support\Config\SiteConfig;
use App\Support\UserDisplay;
use Illuminate\Support\HtmlString;

/**
 * Builds the top-uploaders, disclaimer, and browser-note sections of
 * the index page. Extracted from IndexPageService to keep both classes
 * under the 400-line ratchet.
 */
final class IndexMetaSectionBuilder
{
    public function __construct(
        private readonly IndexRepository $indexRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildTopUploaders(): array
    {
        if (! SiteConfig::current()->main->showTopUploader()) {
            return ['show' => false];
        }

        $allUploaders = $this->indexRepository->getTopUploaders(10);
        if ($allUploaders->isEmpty()) {
            return ['show' => false];
        }

        AssetAppender::css('.tr-top-uploader-tab>[data-table] {cursor: pointer}', 'footer', false);
        $toggleJs = <<<'JS'
document.querySelector(".tr-top-uploader-tab").addEventListener("click", function (e) {
    var td = e.target.closest("[data-table]");
    if (!td || td.classList.contains("nx-colhead")) return;
    var siblings = td.parentNode.children;
    for (var i = 0; i < siblings.length; i++) {
        siblings[i].classList.remove("nx-colhead");
    }
    td.classList.add("nx-colhead");
    var tables = document.querySelectorAll(".top-uploader");
    tables.forEach(function (t) { t.classList.add('nx-hidden'); });
    var target = document.querySelectorAll("." + td.getAttribute("data-table"));
    target.forEach(function (t) {
        t.classList.remove('nx-hidden');
        t.style.opacity = '0';
        t.style.transition = 'opacity 0.2s';
        requestAnimationFrame(function () { t.style.opacity = '1'; });
    });
})
JS;
        AssetAppender::js($toggleJs, 'footer', false);

        $recentUploaders = $this->indexRepository->getTopUploaders(10, 30);

        $buildRows = function ($uploaders): array {
            $rows = [];
            foreach ($uploaders as $ranking => $uploader) {
                $rows[] = [
                    'username' => UserDisplay::username($uploader->id),
                    'count' => $uploader->count,
                    'rank' => $ranking + 1,
                ];
            }

            return $rows;
        };

        return [
            'show' => true,
            'title' => __('legacy/index.top_uploader_title'),
            'toggleHint' => __('legacy/index.top_uploader_toggle_time_range_tab'),
            'recentlyLabel' => __('legacy/index.top_uploader_toggle_time_range_recently'),
            'allLabel' => __('legacy/index.top_uploader_toggle_time_range_all'),
            'colAuthor' => __('legacy/index.col_author'),
            'colCounts' => __('legacy/index.col_counts'),
            'colRanking' => __('legacy/index.col_ranking'),
            'allRows' => $buildRows($allUploaders),
            'recentRows' => $buildRows($recentUploaders),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDisclaimer(): array
    {
        $siteName = Setting::getSiteName();

        return [
            'show' => true,
            'title' => __('legacy/index.text_disclaimer'),
            'content' => sprintf(__('legacy/index.text_disclaimer_content'), $siteName, $siteName),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildBrowserNote(): array
    {
        return [
            'show' => true,
            'note' => new HtmlString((string) (__('legacy/index.text_browser_note'))),
        ];
    }
}
