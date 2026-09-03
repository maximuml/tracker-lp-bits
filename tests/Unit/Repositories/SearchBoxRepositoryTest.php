<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\UserClass;
use App\Exceptions\InsufficientPermissionException;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\SearchBoxRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for SearchBoxRepository.
 *
 * Covers getAllRows(), getTaxonomyRows(), getTaxonomyList(), getList(),
 * store(), update(), getDetail(), delete(), listIcon(), getOrderedIds(),
 * findForCategoryTable(), and getCategoriesForTable().
 */
final class SearchBoxRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private SearchBoxRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        SearchBox::flushEventListeners();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('torrent_tags')->delete();
        DB::table('torrents')->delete();
        DB::table('categories')->delete();
        DB::table('searchbox')->delete();
        DB::table('caticons')->delete();
        DB::table('sources')->delete();
        DB::table('media')->delete();
        DB::table('codecs')->delete();
        DB::table('standards')->delete();
        DB::table('processings')->delete();
        DB::table('audiocodecs')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->repository = new SearchBoxRepository;
    }

    public function test_get_all_rows_returns_empty_when_none(): void
    {
        $this->assertSame([], $this->repository->getAllRows());
    }

    public function test_get_all_rows_returns_rows_indexed_by_id(): void
    {
        $id1 = (int) DB::table('searchbox')->insertGetId([
            'name' => 'Movies',
            'showsubcat' => 0,
            'section_name' => json_encode(['en' => 'Movies']),
            'extra' => json_encode(['key' => 'val']),
        ]);
        $id2 = (int) DB::table('searchbox')->insertGetId([
            'name' => 'TV',
            'showsubcat' => 0,
        ]);

        $result = $this->repository->getAllRows();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey($id1, $result);
        $this->assertArrayHasKey($id2, $result);
        $this->assertSame('Movies', $result[$id1]['name']);
    }

    public function test_get_all_rows_decodes_json_columns(): void
    {
        $id = (int) DB::table('searchbox')->insertGetId([
            'name' => 'Movies',
            'showsubcat' => 0,
            'section_name' => json_encode(['en' => 'Movies']),
            'extra' => json_encode(['key' => 'val']),
        ]);

        $result = $this->repository->getAllRows();

        $this->assertIsArray($result[$id]['extra']);
        $this->assertSame('val', $result[$id]['extra']['key']);
        $this->assertIsArray($result[$id]['section_name']);
        $this->assertSame('Movies', $result[$id]['section_name']['en']);
    }

    public function test_get_taxonomy_rows_returns_empty_when_none(): void
    {
        $result = $this->repository->getTaxonomyRows('sources', 1);

        $this->assertTrue($result->isEmpty());
    }

    public function test_get_taxonomy_rows_filters_by_mode(): void
    {
        DB::table('sources')->insert([
            ['name' => 'Source A', 'sort_index' => 0, 'mode' => 1],
            ['name' => 'Source B', 'sort_index' => 1, 'mode' => 2],
            ['name' => 'Source C', 'sort_index' => 2, 'mode' => 0],
        ]);

        $result = $this->repository->getTaxonomyRows('sources', 1);

        $this->assertCount(2, $result);
        $names = $result->pluck('name')->toArray();
        $this->assertContains('Source A', $names);
        $this->assertContains('Source C', $names);
    }

    public function test_get_taxonomy_rows_orders_by_sort_index_desc(): void
    {
        DB::table('sources')->insert([
            ['name' => 'Low', 'sort_index' => 0, 'mode' => 1],
            ['name' => 'High', 'sort_index' => 5, 'mode' => 1],
        ]);

        $result = $this->repository->getTaxonomyRows('sources', 1);

        $this->assertSame('High', $result->first()->name); // @phpstan-ignore property.nonObject
    }

    public function test_get_taxonomy_list_returns_array_of_arrays(): void
    {
        DB::table('sources')->insert([
            ['name' => 'Source A', 'sort_index' => 0, 'mode' => 1],
        ]);

        $result = $this->repository->getTaxonomyList('sources', 1);

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]);
        $this->assertSame('Source A', $result[0]['name']);
    }

    public function test_get_list_returns_paginated_results(): void
    {
        SearchBox::factory()->create();
        SearchBox::factory()->create();

        $paginator = $this->repository->getList([]);

        $this->assertCount(2, $paginator->items());
    }

    public function test_get_list_sorts_by_allowed_columns(): void
    {
        /** @var SearchBox $first */
        $first = SearchBox::factory()->create(['name' => 'Alpha']);
        SearchBox::factory()->create(['name' => 'Beta']);

        $paginator = $this->repository->getList(['sort_field' => 'name', 'sort_type' => 'asc']);

        $items = $paginator->items();
        $this->assertSame('Alpha', $items[0]->name);
        $this->assertSame($first->id, $items[0]->id);
    }

    public function test_get_list_falls_back_to_id_for_invalid_sort(): void
    {
        /** @var SearchBox $first */
        $first = SearchBox::factory()->create();
        SearchBox::factory()->create();

        $paginator = $this->repository->getList(['sort_field' => 'evil', 'sort_type' => 'asc']);

        $items = $paginator->items();
        $this->assertSame($first->id, $items[0]->id);
    }

    public function test_store_creates_search_box(): void
    {
        $model = $this->repository->store([
            'name' => 'NewBox',
            'catsperrow' => 5,
            'catpadding' => 25,
            'showsubcat' => 1,
            'showsource' => 1,
            'showmedium' => 1,
            'showcodec' => 1,
            'showstandard' => 1,
            'showprocessing' => 1,
            'showaudiocodec' => 1,
        ]);

        $this->assertInstanceOf(SearchBox::class, $model);
        $this->assertDatabaseHas('searchbox', ['name' => 'NewBox']);
    }

    public function test_update_modifies_search_box(): void
    {
        /** @var SearchBox $box */
        $box = SearchBox::factory()->create();

        $model = $this->repository->update(['name' => 'Updated'], $box->id);

        $this->assertSame($box->id, $model->id);
        $this->assertSame('Updated', $model->name);
    }

    public function test_get_detail_returns_model_when_found(): void
    {
        /** @var SearchBox $box */
        $box = SearchBox::factory()->create();

        $model = $this->repository->getDetail($box->id);

        $this->assertInstanceOf(SearchBox::class, $model);
        $this->assertSame($box->id, $model->id);
    }

    public function test_get_detail_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getDetail(999999);
    }

    public function test_delete_removes_search_box(): void
    {
        /** @var SearchBox $box */
        $box = SearchBox::factory()->create();

        $result = $this->repository->delete($box->id);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('searchbox')->where('id', $box->id)->count());
    }

    public function test_delete_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->delete(999999);
    }

    public function test_list_icon_returns_empty_collection_when_no_boxes(): void
    {
        $result = $this->repository->listIcon([99999]);

        $this->assertTrue($result->isEmpty());
    }

    public function test_list_icon_returns_icons_for_categories(): void
    {
        $iconId = (int) DB::table('caticons')->insertGetId(['name' => 'TestIcon', 'folder' => 'test']);
        /** @var SearchBox $box */
        $box = SearchBox::factory()->create();
        DB::table('categories')->insert([
            'name' => 'Cat A',
            'mode' => $box->id,
            'class_name' => 'cat_a',
            'icon_id' => $iconId,
            'sort_index' => 0,
        ]);

        $result = $this->repository->listIcon([$box->id]);

        $this->assertCount(1, $result);
        $this->assertSame('TestIcon', $result->first()->name);
    }

    public function test_get_ordered_ids_returns_empty_when_none(): void
    {
        $this->assertSame([], $this->repository->getOrderedIds());
    }

    public function test_get_ordered_ids_returns_ids_ordered(): void
    {
        /** @var SearchBox $box1 */
        $box1 = SearchBox::factory()->create();
        /** @var SearchBox $box2 */
        $box2 = SearchBox::factory()->create();

        $result = $this->repository->getOrderedIds();

        $this->assertSame([$box1->id, $box2->id], $result);
    }

    public function test_find_for_category_table_returns_search_box_with_categories(): void
    {
        /** @var SearchBox $box */
        $box = SearchBox::factory()->create();
        DB::table('categories')->insert([
            'name' => 'Cat A',
            'mode' => $box->id,
            'class_name' => 'cat_a',
            'icon_id' => 0,
            'sort_index' => 0,
        ]);

        $result = $this->repository->findForCategoryTable($box->id);

        $this->assertInstanceOf(SearchBox::class, $result);
        $this->assertSame($box->id, $result->id);
        $this->assertTrue($result->relationLoaded('categories'));
    }

    public function test_find_for_category_table_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->findForCategoryTable(999999);
    }

    public function test_get_categories_for_table_returns_categories(): void
    {
        /** @var SearchBox $box */
        $box = SearchBox::factory()->create();
        DB::table('categories')->insert([
            'name' => 'Cat A',
            'mode' => $box->id,
            'class_name' => 'cat_a',
            'icon_id' => 0,
            'sort_index' => 1,
        ]);
        DB::table('categories')->insert([
            'name' => 'Cat B',
            'mode' => $box->id,
            'class_name' => 'cat_b',
            'icon_id' => 0,
            'sort_index' => 2,
        ]);

        $result = $this->repository->getCategoriesForTable($box);

        $this->assertCount(2, $result);
    }

    public function test_get_categories_for_table_with_select_unselect_adds_extra(): void
    {
        /** @var SearchBox $box */
        $box = SearchBox::factory()->create();

        $result = $this->repository->getCategoriesForTable($box, true);

        // The extra "unselect" category is pushed
        $this->assertCount(1, $result);
        $this->assertSame(-1, (int) $result->last()->mode); // @phpstan-ignore property.nonObject
    }

    public function test_delete_category_throws_when_torrents_exist(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var SearchBox $box */
        $box = SearchBox::factory()->create();
        $catId = (int) DB::table('categories')->insertGetId([
            'name' => 'Cat A',
            'mode' => $box->id,
            'class_name' => 'cat_a',
            'icon_id' => 0,
            'sort_index' => 0,
        ]);
        Torrent::factory()->category($catId)->create();

        $this->expectException(\RuntimeException::class);

        $this->repository->deleteCategory($catId);
    }

    public function test_delete_category_removes_when_no_torrents(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::STAFFLEADER->value)->create();
        Auth::login($user);

        /** @var SearchBox $box */
        $box = SearchBox::factory()->create();
        $catId = (int) DB::table('categories')->insertGetId([
            'name' => 'Cat A',
            'mode' => $box->id,
            'class_name' => 'cat_a',
            'icon_id' => 0,
            'sort_index' => 0,
        ]);

        $deleted = $this->repository->deleteCategory($catId);

        $this->assertSame(1, $deleted);
        $this->assertSame(0, DB::table('categories')->where('id', $catId)->count());
    }

    public function test_delete_category_throws_when_not_sysop(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::USER->value)->create();
        Auth::login($user);

        $this->expectException(InsufficientPermissionException::class);

        $this->repository->deleteCategory(1);
    }
}
