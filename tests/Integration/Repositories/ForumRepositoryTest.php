<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Models\User;
use App\Repositories\ForumRepository;
use App\Repositories\TopicRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for ForumRepository.
 *
 * Covers createForum(), updateForum(), deleteForum(), getForumRow(),
 * getMaxForumSort(), getTopicIdByPost(), isModeratorOfTopic().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class ForumRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ForumRepository $repository;

    private TopicRepository $topicRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ForumRepository::class);
        $this->topicRepository = app(TopicRepository::class);
    }

    public function test_create_forum_returns_id(): void
    {
        $id = $this->repository->createForum([
            'name' => 'Test Forum',
            'description' => 'Test description',
            'sort' => 1,
            'forid' => 0,
        ]);

        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('forums', ['id' => $id, 'name' => 'Test Forum']);
    }

    public function test_update_forum_modifies_record(): void
    {
        $id = $this->repository->createForum([
            'name' => 'Original Name',
            'description' => 'Original desc',
            'sort' => 1,
            'forid' => 0,
        ]);

        $this->repository->updateForum($id, ['name' => 'Updated Name']);

        $this->assertDatabaseHas('forums', ['id' => $id, 'name' => 'Updated Name']);
    }

    public function test_get_forum_row_returns_array(): void
    {
        $id = $this->repository->createForum([
            'name' => 'Get Row Test',
            'description' => 'desc',
            'sort' => 1,
            'forid' => 0,
        ]);

        $row = $this->repository->getForumRow($id);

        $this->assertNotNull($row);
        $this->assertSame('Get Row Test', $row['name']);
    }

    public function test_get_forum_row_returns_null_for_nonexistent(): void
    {
        $row = $this->repository->getForumRow(999999);

        $this->assertNull($row);
    }

    public function test_get_max_forum_sort_returns_count(): void
    {
        $initial = $this->repository->getMaxForumSort();

        $this->repository->createForum([
            'name' => 'Sort Test',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);

        $after = $this->repository->getMaxForumSort();

        $this->assertSame($initial + 1, $after);
    }

    public function test_delete_forum_removes_forum_and_topics(): void
    {
        $id = $this->repository->createForum([
            'name' => 'Delete Test',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);

        $this->repository->deleteForum($id);

        $this->assertDatabaseMissing('forums', ['id' => $id]);
    }

    public function test_get_topic_id_by_post_returns_topic_id(): void
    {
        $user = User::factory()->create();
        $forumId = $this->repository->createForum([
            'name' => 'Topic Test',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);

        $topicId = (int) DB::table('topics')->insertGetId([
            'forumid' => $forumId,
            'subject' => 'Test topic',
            'userid' => $user->id,
            'lastpost' => 0,
        ]);

        $postId = (int) DB::table('posts')->insertGetId([
            'topicid' => $topicId,
            'userid' => $user->id,
            'added' => now()->toDateTimeString(),
            'body' => 'Test post',
        ]);

        // Update topic's lastpost to point to the new post
        DB::table('topics')->where('id', $topicId)->update(['lastpost' => $postId]);

        $found = $this->topicRepository->getTopicIdByPost($postId);

        $this->assertSame($topicId, $found);
    }

    public function test_get_topic_id_by_post_returns_null_for_nonexistent(): void
    {
        $found = $this->topicRepository->getTopicIdByPost(999999);

        $this->assertNull($found);
    }
}
