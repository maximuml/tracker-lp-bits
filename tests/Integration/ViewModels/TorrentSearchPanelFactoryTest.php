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

    public function test_category_and_taxonomy_links_use_catalog_urls(): void
    {
        $mode = (int) DB::table('searchbox')->insertGetId([
            'name' => 'taxonomy-hrefs',
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
                $this->assertStringStartsWith('/web/torrents?', $cell['href']);
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
                    $this->assertStringStartsWith('/web/torrents?', $cell['href']);
                }
            }
        }
        $this->assertGreaterThan(0, $taxonomyCellCount);
    }
}
