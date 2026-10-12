<?php

declare(strict_types=1);

namespace Tests\Integration\ViewModels;

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
}
