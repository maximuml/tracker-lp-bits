<?php

declare(strict_types=1);

namespace Tests\Integration\ViewModels;

use App\Models\SearchBox;
use App\Models\Torrent;
use App\ViewModels\TorrentSearchPanelFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TorrentSearchPanelFactoryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_category_and_taxonomy_cells_preserve_checkbox_names(): void
    {
        $mode = (int) DB::table('searchbox')->insertGetId([
            'name' => 'taxonomy-chips',
            'showsubcat' => 1,
            'showmedium' => 1,
            'catsperrow' => 7,
        ]);

        DB::table('categories')->insert([
            'mode' => $mode,
            'class_name' => 'taxonomy_hrefs',
            'name' => 'Regression Category',
            'image' => 'regression.gif',
            'sort_index' => 1,
        ]);

        DB::table('media')->insert([
            'mode' => $mode,
            'name' => 'Regression Media',
            'sort_index' => 1,
        ]);

        $viewModel = $this->app->make(TorrentSearchPanelFactory::class)->create($mode, '', null, []);

        $categoryCellCount = 0;
        foreach ($viewModel->categoryRows as $row) {
            foreach ($row as $cell) {
                if ($cell['selectAll']) {
                    continue;
                }

                $categoryCellCount++;
                $this->assertStringStartsWith('cat', $cell['checkboxName']);
            }
        }
        $this->assertGreaterThan(0, $categoryCellCount);

        $taxonomyCellCount = 0;
        foreach ($viewModel->taxonomySections as $section) {
            foreach ($section['rows'] as $row) {
                foreach ($row as $cell) {
                    if ($cell['selectAll']) {
                        continue;
                    }

                    $taxonomyCellCount++;
                    $this->assertStringStartsWith('medium', $cell['checkboxName']);
                }
            }
        }
        $this->assertGreaterThan(0, $taxonomyCellCount);
    }

    public function test_advanced_filter_count_includes_taxonomy_status_and_ranges_only(): void
    {
        $mode = (int) DB::table('searchbox')->insertGetId([
            'name' => 'advanced-count',
            'showsubcat' => 1,
            'showmedium' => 1,
            'catsperrow' => 7,
        ]);
        $mediaId = (int) DB::table('media')->insertGetId([
            'mode' => $mode,
            'name' => 'Count Media',
            'sort_index' => 1,
        ]);

        $factory = $this->app->make(TorrentSearchPanelFactory::class);
        $defaults = $factory->create($mode, '', null, []);
        $this->assertSame(0, $defaults->advancedFilterCount);

        $viewModel = $factory->create(
            $mode,
            "medium{$mediaId}=1&incldead=2&size_begin=1&search=ignored&cat1=1",
            null,
            [],
            [
                "medium{$mediaId}" => '1',
                'incldead' => '2',
                'size_begin' => '1',
                'search' => 'ignored',
                'cat1' => '1',
            ],
        );

        $this->assertSame(3, $viewModel->advancedFilterCount);
    }

    public function test_applied_filters_have_canonical_removal_urls_and_ignore_notification_fallback(): void
    {
        $mode = (int) DB::table('searchbox')->insertGetId([
            'name' => 'applied-filter-test',
            'showsubcat' => 1,
            'showmedium' => 1,
            'catsperrow' => 7,
        ]);
        $categoryId = (int) DB::table('categories')->insertGetId([
            'mode' => $mode,
            'class_name' => 'applied_filter_category',
            'name' => 'Applied Category',
            'image' => 'applied.gif',
            'sort_index' => 1,
        ]);
        $mediumId = (int) DB::table('media')->insertGetId([
            'mode' => $mode,
            'name' => 'Applied Medium',
            'sort_index' => 1,
        ]);
        $factory = $this->app->make(TorrentSearchPanelFactory::class);
        $input = [
            'search' => 'foo',
            'search_area' => '1',
            'notnewword' => '1',
            "cat{$categoryId}" => '1',
            "medium{$mediumId}" => '1',
            'incldead' => '2',
            'size_begin' => '1',
            'size_end' => '10',
            'sort' => '4',
            'type' => 'desc',
            'page' => '2',
        ];

        $viewModel = $factory->create($mode, '', null, [], $input);
        $filters = $viewModel->appliedFilters;
        $base = "/web/torrents?cat{$categoryId}=1&medium{$mediumId}=1&incldead=2&size_begin=1&size_end=10&sort=4&type=desc";
        $this->assertSame([
            ['label' => 'Search: “foo”', 'removeUrl' => $base],
            ['label' => 'Applied Category', 'removeUrl' => "/web/torrents?search=foo&search_area=1&notnewword=1&medium{$mediumId}=1&incldead=2&size_begin=1&size_end=10&sort=4&type=desc"],
            ['label' => 'Media: Applied Medium', 'removeUrl' => "/web/torrents?search=foo&search_area=1&notnewword=1&cat{$categoryId}=1&incldead=2&size_begin=1&size_end=10&sort=4&type=desc"],
            ['label' => 'Show Dead/Active: dead', 'removeUrl' => "/web/torrents?search=foo&search_area=1&notnewword=1&cat{$categoryId}=1&medium{$mediumId}=1&size_begin=1&size_end=10&sort=4&type=desc"],
            ['label' => 'Size range(GB): 1 – 10', 'removeUrl' => "/web/torrents?search=foo&search_area=1&notnewword=1&cat{$categoryId}=1&medium{$mediumId}=1&incldead=2&sort=4&type=desc"],
        ], $filters);

        $searchModes = SearchBox::listSearchModes();
        $alternateSearchMode = (string) SearchBox::getDefaultSearchMode() === '0' ? 2 : 0;
        $searchModeFilters = $factory->create($mode, '', null, [], ['search_mode' => (string) $alternateSearchMode]);
        $this->assertSame('Search mode: '.$searchModes[$alternateSearchMode], $searchModeFilters->appliedFilters[0]['label']);

        $approvalStatuses = Torrent::listApprovalStatus(true);
        $approvalStatus = (int) array_key_first($approvalStatuses);
        $approvalFilters = $factory->create(
            $mode,
            '',
            null,
            [],
            ['approval_status' => (string) $approvalStatus],
            true,
        );
        $this->assertSame(
            'Approval status: '.(string) $approvalStatuses[$approvalStatus],
            $approvalFilters->appliedFilters[0]['label'],
        );

        $this->assertSame('/web/torrents', $viewModel->resetUrl);

        $fallbackOnly = $factory->create($mode, "cat{$categoryId}=1", "[cat{$categoryId}]", []);
        $this->assertSame([], $fallbackOnly->appliedFilters);

        $scalarFields = $factory->create($mode, '', null, [], [
            'cat' => (string) $categoryId,
            'medium' => (string) $mediumId,
            'page' => '2',
            'sort' => '4',
        ]);
        $this->assertSame([
            ['label' => 'Applied Category', 'removeUrl' => "/web/torrents?medium={$mediumId}&sort=4"],
            ['label' => 'Media: Applied Medium', 'removeUrl' => "/web/torrents?cat={$categoryId}&sort=4"],
        ], $scalarFields->appliedFilters);

        $defaults = $factory->create($mode, '', null, [], [
            'incldead' => '1',
            'spstate' => '0',
            'inclbookmarked' => '0',
        ]);
        $this->assertSame([], $defaults->appliedFilters);

        $tagId = (int) DB::table('tags')->insertGetId([
            'name' => 'Applied Tag',
            'color' => '#fff',
            'priority' => 1,
            'mode' => $mode,
        ]);
        $tagFilter = $factory->create($mode, '', null, [], ['tag_id' => (string) $tagId]);
        $this->assertSame([
            ['label' => 'Applied Tag', 'removeUrl' => '/web/torrents'],
        ], $tagFilter->appliedFilters);
    }
}
