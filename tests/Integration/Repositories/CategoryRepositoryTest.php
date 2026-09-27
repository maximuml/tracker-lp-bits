<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\CategoryRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for CategoryRepository.
 *
 * Covers tableNameForType(), getRecord(),
 * getIconRows(), getCategoryRows(), findSecondIcon(), and
 * getCategoriesByMode().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class CategoryRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private CategoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('secondicons')->delete();
        DB::table('audiocodecs')->delete();
        DB::table('processings')->delete();
        DB::table('standards')->delete();
        DB::table('codecs')->delete();
        DB::table('media')->delete();
        DB::table('sources')->delete();
        DB::table('caticons')->delete();
        DB::table('searchbox')->delete();
        DB::table('categories')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new CategoryRepository;
    }

    public function test_table_name_for_type_maps_known_types(): void
    {
        $this->assertSame('categories', $this->repository->tableNameForType('category'));
        $this->assertSame('sources', $this->repository->tableNameForType('source'));
        $this->assertSame('media', $this->repository->tableNameForType('medium'));
        $this->assertSame('codecs', $this->repository->tableNameForType('codec'));
        $this->assertSame('standards', $this->repository->tableNameForType('standard'));
        $this->assertSame('processings', $this->repository->tableNameForType('processing'));
        $this->assertSame('audiocodecs', $this->repository->tableNameForType('audiocodec'));
        $this->assertSame('searchbox', $this->repository->tableNameForType('searchbox'));
        $this->assertSame('caticons', $this->repository->tableNameForType('caticon'));
        $this->assertSame('secondicons', $this->repository->tableNameForType('secondicon'));
    }

    public function test_table_name_for_type_returns_input_for_unknown_type(): void
    {
        $this->assertSame('unknown', $this->repository->tableNameForType('unknown'));
    }

    public function test_get_record_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getRecord('sources', 99999));
    }

    public function test_get_record_returns_array_when_found(): void
    {
        $id = (int) DB::table('sources')->insertGetId([
            'name' => 'Test Source',
            'sort_index' => 0,
            'mode' => 1,
        ]);

        $result = $this->repository->getRecord('sources', $id);

        $this->assertNotNull($result);
        $this->assertSame($id, (int) $result['id']);
        $this->assertSame('Test Source', $result['name']);
    }

    public function test_get_icon_rows_returns_empty_when_none(): void
    {
        $this->assertSame([], $this->repository->getIconRows());
    }

    public function test_get_icon_rows_returns_rows_indexed_by_id(): void
    {
        $id1 = (int) DB::table('caticons')->insertGetId(['name' => 'Icon A', 'folder' => 'a']);
        $id2 = (int) DB::table('caticons')->insertGetId(['name' => 'Icon B', 'folder' => 'b']);

        $result = $this->repository->getIconRows();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey($id1, $result);
        $this->assertArrayHasKey($id2, $result);
        $this->assertSame('Icon A', $result[$id1]['name']);
    }

    public function test_get_category_rows_returns_empty_when_none(): void
    {
        $this->assertSame([], $this->repository->getCategoryRows());
    }

    public function test_get_category_rows_returns_rows_indexed_by_id(): void
    {
        $searchboxId = (int) DB::table('searchbox')->insertGetId(['name' => 'SB', 'showsubcat' => 0]);
        $id1 = (int) DB::table('categories')->insertGetId([
            'name' => 'Cat A', 'mode' => $searchboxId, 'sort_index' => 0,
        ]);
        $id2 = (int) DB::table('categories')->insertGetId([
            'name' => 'Cat B', 'mode' => $searchboxId, 'sort_index' => 1,
        ]);

        $result = $this->repository->getCategoryRows();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey($id1, $result);
        $this->assertArrayHasKey($id2, $result);
        $this->assertSame('Cat A', $result[$id1]['name']);
        $this->assertSame('SB', $result[$id1]['catmodename']);
    }

    public function test_find_second_icon_returns_null_when_no_match(): void
    {
        $result = $this->repository->findSecondIcon([
            'source' => 1,
            'medium' => 0,
            'codec' => 0,
            'standard' => 0,
            'processing' => 0,
            'audiocodec' => 0,
            'search_box_id' => 1,
        ]);

        $this->assertNull($result);
    }

    public function test_find_second_icon_matches_exact_values(): void
    {
        DB::table('secondicons')->insert([
            'source' => 1,
            'medium' => 2,
            'codec' => 0,
            'standard' => 0,
            'processing' => 0,
            'audiocodec' => 0,
            'name' => 'Match Icon',
            'image' => 'match.png',
            'mode' => 1,
        ]);

        $result = $this->repository->findSecondIcon([
            'source' => 1,
            'medium' => 2,
            'codec' => 0,
            'standard' => 0,
            'processing' => 0,
            'audiocodec' => 0,
            'search_box_id' => 1,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('Match Icon', $result['name']);
    }

    public function test_find_second_icon_matches_with_zero_fallbacks(): void
    {
        // A secondicon with all zeros should match any query
        DB::table('secondicons')->insert([
            'source' => 0,
            'medium' => 0,
            'codec' => 0,
            'standard' => 0,
            'processing' => 0,
            'audiocodec' => 0,
            'name' => 'Fallback Icon',
            'image' => 'fallback.png',
            'mode' => 0,
        ]);

        $result = $this->repository->findSecondIcon([
            'source' => 5,
            'medium' => 3,
            'codec' => 0,
            'standard' => 0,
            'processing' => 0,
            'audiocodec' => 0,
            'search_box_id' => 0,
        ]);

        $this->assertNotNull($result);
        $this->assertSame('Fallback Icon', $result['name']);
    }

    public function test_get_categories_by_mode_returns_empty_when_none(): void
    {
        $this->assertSame([], $this->repository->getCategoriesByMode(999));
    }

    public function test_get_categories_by_mode_returns_filtered_categories(): void
    {
        DB::table('categories')->insert([
            ['name' => 'Cat A', 'mode' => 1, 'sort_index' => 1],
            ['name' => 'Cat B', 'mode' => 2, 'sort_index' => 1],
            ['name' => 'Cat C', 'mode' => 1, 'sort_index' => 2],
        ]);

        $result = $this->repository->getCategoriesByMode(1);

        $this->assertCount(2, $result);
        // Ordered by sort_index desc
        $this->assertSame('Cat C', $result[0]['name']);
        $this->assertSame('Cat A', $result[1]['name']);
    }
}
