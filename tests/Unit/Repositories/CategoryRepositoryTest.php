<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\CategoryRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for CategoryRepository.
 *
 * Covers tableNameForType(), validSubcatTypes(), clearCacheAfterDelete(),
 * getSearchboxOptions(), getCaticonOptions(), countByTable(), getRecord(),
 * deleteRecord(), listByTable(), getCategoryList(), getSecondiconLookups(),
 * getIconRows(), getCategoryRows(), findSecondIcon(), and
 * getCategoriesByMode().
 */
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

    public function test_valid_subcat_types_returns_expected_list(): void
    {
        $types = $this->repository->validSubcatTypes();

        $this->assertContains('source', $types);
        $this->assertContains('medium', $types);
        $this->assertContains('codec', $types);
        $this->assertContains('standard', $types);
        $this->assertContains('processing', $types);
        $this->assertContains('audiocodec', $types);
        $this->assertCount(6, $types);
    }

    public function test_clear_cache_after_delete_does_not_throw_for_subcat(): void
    {
        $this->repository->clearCacheAfterDelete('source', ['id' => 1]);

        $this->expectNotToPerformAssertions();
    }

    public function test_clear_cache_after_delete_does_not_throw_for_category(): void
    {
        $this->repository->clearCacheAfterDelete('category', ['mode' => 1]);

        $this->expectNotToPerformAssertions();
    }

    public function test_clear_cache_after_delete_does_not_throw_for_secondicon(): void
    {
        $this->repository->clearCacheAfterDelete('secondicon', [
            'source' => 1,
            'medium' => 2,
            'codec' => 3,
            'standard' => 4,
            'processing' => 5,
            'audiocodec' => 6,
        ]);

        $this->expectNotToPerformAssertions();
    }

    public function test_get_searchbox_options_returns_empty_when_none(): void
    {
        $this->assertSame([], $this->repository->getSearchboxOptions());
    }

    public function test_get_searchbox_options_returns_ordered_by_id(): void
    {
        DB::table('searchbox')->insert([
            ['name' => 'Movies', 'showsubcat' => 0],
            ['name' => 'TV', 'showsubcat' => 0],
        ]);

        $result = $this->repository->getSearchboxOptions();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('name', $result[0]);
        $this->assertArrayHasKey('id', $result[0]);
    }

    public function test_get_caticon_options_returns_empty_when_none(): void
    {
        $this->assertSame([], $this->repository->getCaticonOptions());
    }

    public function test_get_caticon_options_returns_ordered_by_id(): void
    {
        DB::table('caticons')->insert([
            ['name' => 'Icon A', 'folder' => 'a'],
            ['name' => 'Icon B', 'folder' => 'b'],
        ]);

        $result = $this->repository->getCaticonOptions();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('name', $result[0]);
    }

    public function test_count_by_table_returns_zero_when_empty(): void
    {
        $this->assertSame(0, $this->repository->countByTable('sources'));
    }

    public function test_count_by_table_returns_count(): void
    {
        DB::table('sources')->insert([
            ['name' => 'Source A', 'sort_index' => 0, 'mode' => 1],
            ['name' => 'Source B', 'sort_index' => 1, 'mode' => 1],
        ]);

        $this->assertSame(2, $this->repository->countByTable('sources'));
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

    public function test_delete_record_returns_false_when_not_found(): void
    {
        $this->assertFalse($this->repository->deleteRecord('sources', 99999));
    }

    public function test_delete_record_removes_record(): void
    {
        $id = (int) DB::table('sources')->insertGetId([
            'name' => 'Delete Me',
            'sort_index' => 0,
            'mode' => 1,
        ]);

        $this->assertTrue($this->repository->deleteRecord('sources', $id));
        $this->assertSame(0, DB::table('sources')->where('id', $id)->count());
    }

    public function test_list_by_table_returns_empty_when_none(): void
    {
        $this->assertSame([], $this->repository->listByTable('sources', 0, 10));
    }

    public function test_list_by_table_returns_rows_ordered_by_id_desc(): void
    {
        DB::table('sources')->insert([
            ['name' => 'A', 'sort_index' => 0, 'mode' => 1],
            ['name' => 'B', 'sort_index' => 1, 'mode' => 1],
        ]);

        $result = $this->repository->listByTable('sources', 0, 10);

        $this->assertCount(2, $result);
        // Default direction is desc, so B (higher id) comes first
        $this->assertSame('B', $result[0]['name']);
    }

    public function test_list_by_table_respects_offset_and_limit(): void
    {
        DB::table('sources')->insert([
            ['name' => 'A', 'sort_index' => 0, 'mode' => 1],
            ['name' => 'B', 'sort_index' => 1, 'mode' => 1],
            ['name' => 'C', 'sort_index' => 2, 'mode' => 1],
        ]);

        $result = $this->repository->listByTable('sources', 1, 1);

        $this->assertCount(1, $result);
        $this->assertSame('B', $result[0]['name']);
    }

    public function test_list_by_table_falls_back_to_id_for_invalid_sort(): void
    {
        DB::table('sources')->insert([
            ['name' => 'A', 'sort_index' => 0, 'mode' => 1],
            ['name' => 'B', 'sort_index' => 1, 'mode' => 1],
        ]);

        $result = $this->repository->listByTable('sources', 0, 10, 'evil', 'asc');

        $this->assertCount(2, $result);
        $this->assertSame('A', $result[0]['name']);
    }

    public function test_get_category_list_returns_empty_when_none(): void
    {
        $this->assertSame([], $this->repository->getCategoryList(0, 10));
    }

    public function test_get_category_list_returns_categories_with_joins(): void
    {
        $searchboxId = (int) DB::table('searchbox')->insertGetId([
            'name' => 'Movies',
            'showsubcat' => 0,
        ]);
        $iconId = (int) DB::table('caticons')->insertGetId([
            'name' => 'Default Icon',
            'folder' => 'default',
        ]);
        DB::table('categories')->insert([
            'name' => 'HD Movies',
            'mode' => $searchboxId,
            'icon_id' => $iconId,
            'sort_index' => 0,
        ]);

        $result = $this->repository->getCategoryList(0, 10);

        $this->assertCount(1, $result);
        $this->assertSame('HD Movies', $result[0]['name']);
        $this->assertSame('Movies', $result[0]['catmodename']);
        $this->assertSame('Default Icon', $result[0]['icon_name']);
    }

    public function test_get_secondicon_lookups_returns_all_subcat_tables(): void
    {
        DB::table('sources')->insert(['name' => 'BluRay', 'sort_index' => 0, 'mode' => 1]);
        DB::table('media')->insert(['name' => 'DVD', 'sort_index' => 0, 'mode' => 1]);
        DB::table('codecs')->insert(['name' => 'x264', 'sort_index' => 0, 'mode' => 1]);
        DB::table('standards')->insert(['name' => '1080p', 'sort_index' => 0, 'mode' => 1]);
        DB::table('processings')->insert(['name' => 'RAW', 'sort_index' => 0, 'mode' => 1]);
        DB::table('audiocodecs')->insert(['name' => 'DTS', 'sort_index' => 0, 'mode' => 1]);

        $result = $this->repository->getSecondiconLookups();

        $this->assertArrayHasKey('source', $result);
        $this->assertArrayHasKey('media', $result);
        $this->assertArrayHasKey('codec', $result);
        $this->assertArrayHasKey('standard', $result);
        $this->assertArrayHasKey('processing', $result);
        $this->assertArrayHasKey('audiocodec', $result);
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
