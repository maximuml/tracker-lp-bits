<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Tag;
use App\Models\Torrent;
use App\Repositories\TagRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for TagRepository.
 *
 * Covers getList(), store(), update(), getDetail(), delete(),
 * createBasicQuery(), getOrderByFieldIdString(), syncTorrentTags(),
 * listAll(), buildSelect(), renderCheckbox(), and renderSpan().
 *
 * Static caches ($allTags, $orderByFieldIdString) are reset via reflection
 * in setUp() to avoid cross-test contamination.
 */
final class TagRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TagRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('torrent_tags')->delete();
        DB::table('tags')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->repository = new TagRepository;
        $this->resetStaticCache();
    }

    protected function tearDown(): void
    {
        $this->resetStaticCache();
        parent::tearDown();
    }

    public function test_get_list_returns_paginated_tags(): void
    {
        $this->createTag(['name' => 'Tag A']);
        $this->createTag(['name' => 'Tag B']);

        $paginator = $this->repository->getList([]);

        $this->assertCount(2, $paginator->items());
    }

    public function test_create_basic_query_orders_by_priority_desc_then_id_desc(): void
    {
        $first = $this->createTag(['name' => 'Low Priority', 'priority' => 1]);
        $second = $this->createTag(['name' => 'High Priority', 'priority' => 10]);

        $results = $this->repository->createBasicQuery()->get();

        $this->assertSame($second->id, $results[0]->id);
        $this->assertSame($first->id, $results[1]->id);
    }

    public function test_store_creates_tag(): void
    {
        $tag = $this->repository->store([
            'name' => 'Created Tag',
            'color' => '#ff0000',
            'priority' => 5,
            'mode' => 0,
        ]);

        $this->assertInstanceOf(Tag::class, $tag);
        $this->assertSame('Created Tag', $tag->name);
        $this->assertDatabaseHas('tags', ['name' => 'Created Tag']);
    }

    public function test_update_modifies_tag(): void
    {
        $tag = $this->createTag(['name' => 'Original']);

        $updated = $this->repository->update(['name' => 'Updated'], $tag->id);

        $this->assertSame('Updated', $updated->name);
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Updated']);
    }

    public function test_get_detail_returns_tag_when_found(): void
    {
        $tag = $this->createTag(['name' => 'Find Me']);

        $result = $this->repository->getDetail($tag->id);

        $this->assertInstanceOf(Tag::class, $result);
        $this->assertSame($tag->id, $result->id);
    }

    public function test_get_detail_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getDetail(999999);
    }

    public function test_delete_removes_tag(): void
    {
        $tag = $this->createTag(['name' => 'Delete Me']);

        $result = $this->repository->delete($tag->id);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('tags')->where('id', $tag->id)->count());
    }

    public function test_delete_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->delete(999999);
    }

    public function test_get_order_by_field_id_string_returns_ids(): void
    {
        $tag1 = $this->createTag(['name' => 'Tag 1', 'priority' => 5]);
        $tag2 = $this->createTag(['name' => 'Tag 2', 'priority' => 10]);

        $string = $this->repository->getOrderByFieldIdString();

        // Ordered by priority desc, so tag2 (priority 10) comes first.
        $this->assertSame("{$tag2->id},{$tag1->id}", $string);
    }

    public function test_get_order_by_field_id_string_returns_zero_when_empty(): void
    {
        $this->resetStaticCache();

        $this->assertSame('0', $this->repository->getOrderByFieldIdString());
    }

    public function test_list_all_returns_all_tags(): void
    {
        $this->createTag(['name' => 'Tag A']);
        $this->createTag(['name' => 'Tag B']);

        $result = $this->repository->listAll();

        $this->assertCount(2, $result);
    }

    public function test_list_all_filters_by_search_box_id(): void
    {
        $this->createTag(['name' => 'Global', 'mode' => 0]);
        $this->createTag(['name' => 'Box1', 'mode' => 1]);
        $this->createTag(['name' => 'Box2', 'mode' => 2]);

        $result = $this->repository->listAll(1);

        $this->assertCount(2, $result); // mode 0 and mode 1
        $names = $result->pluck('name')->all();
        $this->assertContains('Global', $names);
        $this->assertContains('Box1', $names);
    }

    public function test_sync_torrent_tags_inserts_records(): void
    {
        $tag1 = $this->createTag(['name' => 'Tag 1']);
        $tag2 = $this->createTag(['name' => 'Tag 2']);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        $torrentId = $torrent->id;

        $this->repository->syncTorrentTags($torrentId, [$tag1->id, $tag2->id]);

        $this->assertSame(2, DB::table('torrent_tags')->where('torrent_id', $torrentId)->count());
    }

    public function test_sync_torrent_tags_with_sync_deletes_old_tags(): void
    {
        $tag1 = $this->createTag(['name' => 'Tag 1']);
        $tag2 = $this->createTag(['name' => 'Tag 2']);
        $tag3 = $this->createTag(['name' => 'Tag 3']);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        $torrentId = $torrent->id;
        $this->repository->syncTorrentTags($torrentId, [$tag1->id, $tag2->id]);

        $this->repository->syncTorrentTags($torrentId, [$tag3->id], true);

        $this->assertSame(1, DB::table('torrent_tags')->where('torrent_id', $torrentId)->count());
        $this->assertSame(1, DB::table('torrent_tags')->where('torrent_id', $torrentId)->where('tag_id', $tag3->id)->count());
    }

    public function test_sync_torrent_tags_with_empty_array_does_nothing(): void
    {
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        $torrentId = $torrent->id;

        $this->repository->syncTorrentTags($torrentId, []);

        $this->assertSame(0, DB::table('torrent_tags')->where('torrent_id', $torrentId)->count());
    }

    public function test_sync_torrent_tags_deduplicates_ids(): void
    {
        $tag1 = $this->createTag(['name' => 'Tag 1']);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        $torrentId = $torrent->id;

        $this->repository->syncTorrentTags($torrentId, [$tag1->id, $tag1->id]);

        $this->assertSame(1, DB::table('torrent_tags')->where('torrent_id', $torrentId)->count());
    }

    public function test_build_select_returns_html_with_options(): void
    {
        $tag = $this->createTag(['name' => 'Option Tag']);

        $html = $this->repository->buildSelect(0, 'tag_id', $tag->id);

        $this->assertStringContainsString('<select name="tag_id">', $html);
        $this->assertStringContainsString('Option Tag', $html);
        $this->assertStringContainsString('selected', $html);
        $this->assertStringContainsString('</select>', $html);
    }

    public function test_build_select_marks_selected_value(): void
    {
        $tag = $this->createTag(['name' => 'Selected Tag']);
        $this->createTag(['name' => 'Other Tag']);

        $html = $this->repository->buildSelect(0, 'tag_id', $tag->id);

        $this->assertStringContainsString('value="'.$tag->id.'" selected', $html);
    }

    public function test_render_checkbox_returns_html_with_labels(): void
    {
        $tag = $this->createTag(['name' => 'Checkbox Tag']);

        $html = $this->repository->renderCheckbox(0, [$tag->id], true);

        $this->assertStringContainsString('<label>', $html);
        $this->assertStringContainsString('Checkbox Tag', $html);
        $this->assertStringContainsString('checked', $html);
    }

    public function test_render_checkbox_without_checked_does_not_mark(): void
    {
        $tag = $this->createTag(['name' => 'Plain Tag']);

        $html = $this->repository->renderCheckbox(0, [], true);

        $this->assertStringContainsString('Plain Tag', $html);
        $this->assertStringNotContainsString(' checked', $html);
    }

    public function test_render_span_returns_html_for_matching_tags(): void
    {
        $tag = $this->createTag(['name' => 'Span Tag', 'color' => '#ff0000']);

        $html = $this->repository->renderSpan(0, [$tag->id]);

        $this->assertStringContainsString('<span', $html);
        $this->assertStringContainsString('Span Tag', $html);
    }

    public function test_render_span_with_wildcard_renders_all(): void
    {
        $this->createTag(['name' => 'Tag One']);
        $this->createTag(['name' => 'Tag Two']);

        $html = $this->repository->renderSpan(0, ['*']);

        $this->assertStringContainsString('Tag One', $html);
        $this->assertStringContainsString('Tag Two', $html);
    }

    public function test_render_span_with_filter_link_wraps_in_anchor(): void
    {
        $tag = $this->createTag(['name' => 'Linked Tag']);

        $html = $this->repository->renderSpan(0, [$tag->id], true);

        $this->assertStringContainsString('<a href="?tag_id='.$tag->id.'">', $html);
    }

    public function test_render_span_returns_empty_when_no_matching_tags(): void
    {
        $this->createTag(['name' => 'Tag One']);

        $html = $this->repository->renderSpan(0, [999999]);

        $this->assertSame('', $html);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTag(array $overrides = []): Tag
    {
        return Tag::query()->create(array_merge([
            'name' => 'Test Tag',
            'color' => '#000000',
            'priority' => 0,
            'mode' => 0,
        ], $overrides));
    }

    private function resetStaticCache(): void
    {
        $reflection = new \ReflectionClass(TagRepository::class);
        $allTags = $reflection->getProperty('allTags');
        $allTags->setValue(null, null);
        $orderBy = $reflection->getProperty('orderByFieldIdString');
        $orderBy->setValue(null, null);
    }
}
