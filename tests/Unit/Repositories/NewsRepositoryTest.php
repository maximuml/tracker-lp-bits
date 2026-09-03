<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\News;
use App\Models\User;
use App\Repositories\NewsRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for NewsRepository.
 *
 * Covers getList(), store(), update(), getDetail(), and delete().
 */
final class NewsRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private NewsRepository $repository;

    private int $userOneId;

    private int $userTwoId;

    protected function setUp(): void
    {
        parent::setUp();
        // Flush model event listeners to avoid event-side issues.
        News::flushEventListeners();
        DB::table('news')->delete();
        $this->repository = new NewsRepository;

        /** @var User $userOne */
        $userOne = User::factory()->create();
        /** @var User $userTwo */
        $userTwo = User::factory()->create();
        $this->userOneId = $userOne->id;
        $this->userTwoId = $userTwo->id;
    }

    public function test_get_list_returns_paginated_results(): void
    {
        $this->insertNews($this->userOneId, 'First News', 'body one');
        $this->insertNews($this->userTwoId, 'Second News', 'body two');

        $paginator = $this->repository->getList([]);

        $this->assertCount(2, $paginator->items());
    }

    public function test_get_list_filters_by_userid(): void
    {
        $this->insertNews($this->userOneId, 'User One News', 'body');
        $this->insertNews($this->userTwoId, 'User Two News', 'body');
        $this->insertNews($this->userOneId, 'Another User One', 'body');

        $paginator = $this->repository->getList(['userid' => $this->userOneId]);

        $items = $paginator->items();
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame($this->userOneId, (int) $item->userid);
        }
    }

    public function test_get_list_sorts_by_added_desc_by_default(): void
    {
        $this->insertNews($this->userOneId, 'Older', 'body', '2025-01-01 00:00:00');
        $this->insertNews($this->userOneId, 'Newer', 'body', '2025-06-01 00:00:00');

        $paginator = $this->repository->getList([]);

        $items = $paginator->items();
        $this->assertSame('Newer', $items[0]->title);
        $this->assertSame('Older', $items[1]->title);
    }

    public function test_get_list_respects_allowed_sort_field(): void
    {
        $this->insertNews($this->userOneId, 'Title B', 'body', '2025-01-01 00:00:00');
        $this->insertNews($this->userTwoId, 'Title A', 'body', '2025-06-01 00:00:00');

        $paginator = $this->repository->getList(['sort_field' => 'userid', 'sort_type' => 'asc']);

        $items = $paginator->items();
        $this->assertSame($this->userOneId, (int) $items[0]->userid);
        $this->assertSame($this->userTwoId, (int) $items[1]->userid);
    }

    public function test_get_list_falls_back_to_id_when_sort_field_not_allowed(): void
    {
        $firstId = $this->insertNews($this->userOneId, 'First', 'body');
        $this->insertNews($this->userTwoId, 'Second', 'body');

        $paginator = $this->repository->getList(['sort_field' => 'evil', 'sort_type' => 'asc']);

        $items = $paginator->items();
        // id is the fallback; asc order means lowest id first.
        $this->assertSame($firstId, (int) $items[0]->id);
    }

    public function test_store_creates_news(): void
    {
        $model = $this->repository->store([
            'userid' => $this->userOneId,
            'title' => 'Created News',
            'body' => 'created body',
            'notify' => true,
        ]);

        $this->assertInstanceOf(News::class, $model);
        $this->assertSame('Created News', $model->title);
        $this->assertDatabaseHas('news', [
            'userid' => $this->userOneId,
            'title' => 'Created News',
        ]);
    }

    public function test_update_modifies_news(): void
    {
        $id = $this->insertNews($this->userOneId, 'Original', 'original body');

        $model = $this->repository->update([
            'title' => 'Updated Title',
            'body' => 'updated body',
        ], $id);

        $this->assertSame($id, $model->id);
        $this->assertSame('Updated Title', $model->title);
    }

    public function test_get_detail_returns_model_when_found(): void
    {
        $id = $this->insertNews($this->userOneId, 'Detail News', 'detail body');

        $model = $this->repository->getDetail($id);

        $this->assertInstanceOf(News::class, $model);
        $this->assertSame($id, $model->id);
        $this->assertSame('Detail News', $model->title);
    }

    public function test_get_detail_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getDetail(999999);
    }

    public function test_delete_removes_news(): void
    {
        $id = $this->insertNews($this->userOneId, 'Delete Me', 'body');

        $result = $this->repository->delete($id);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('news')->where('id', $id)->count());
    }

    public function test_delete_throws_when_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->delete(999999);
    }

    private function insertNews(int $userId, string $title, string $body, ?string $added = null): int
    {
        return (int) DB::table('news')->insertGetId([
            'userid' => $userId,
            'added' => $added ?? now()->toDateTimeString(),
            'body' => $body,
            'title' => $title,
            'notify' => 0,
        ]);
    }
}
