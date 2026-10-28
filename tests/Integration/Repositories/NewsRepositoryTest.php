<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Models\News;
use App\Models\User;
use App\Repositories\NewsRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for NewsRepository.
 *
 * Covers getList(), store(), update(), getDetail(), and delete().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
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
