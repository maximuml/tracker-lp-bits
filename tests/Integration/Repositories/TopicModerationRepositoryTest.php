<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\TopicModerationRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for TopicModerationRepository.
 *
 * Covers deleteTopic(), moveTopic(), updateTopicLocked(),
 * updateTopicSticky(), updateTopicHighlight().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TopicModerationRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TopicModerationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(TopicModerationRepository::class);
    }

    public function test_delete_topic_removes_topic_posts_and_readposts(): void
    {
        $forum = Forum::factory()->create(['topiccount' => 5, 'postcount' => 10]);
        $user = User::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        Post::factory()->create(['topicid' => $topic->id]);
        DB::table('readposts')->insert([
            'userid' => $user->id,
            'topicid' => $topic->id,
            'lastpostread' => 1,
        ]);

        $this->repository->deleteTopic($topic->id, $forum->id, 1);

        $this->assertDatabaseMissing('topics', ['id' => $topic->id]);
        $this->assertDatabaseMissing('posts', ['topicid' => $topic->id]);
        $this->assertDatabaseMissing('readposts', ['topicid' => $topic->id]);
    }

    public function test_move_topic_changes_forum_and_adjusts_counts(): void
    {
        $oldForum = Forum::factory()->create(['topiccount' => 5, 'postcount' => 10]);
        $newForum = Forum::factory()->create(['topiccount' => 3, 'postcount' => 6]);
        $topic = Topic::factory()->create(['forumid' => $oldForum->id]);

        $this->repository->moveTopic($topic->id, $newForum->id, 2, $oldForum->id);

        $this->assertSame($newForum->id, Topic::query()->where('id', $topic->id)->value('forumid'));
        $this->assertSame(4, (int) Forum::query()->where('id', $oldForum->id)->value('topiccount'));
        $this->assertSame(8, (int) Forum::query()->where('id', $oldForum->id)->value('postcount'));
        $this->assertSame(4, (int) Forum::query()->where('id', $newForum->id)->value('topiccount'));
        $this->assertSame(8, (int) Forum::query()->where('id', $newForum->id)->value('postcount'));
    }

    public function test_move_topic_returns_true_when_same_forum(): void
    {
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id]);

        $result = $this->repository->moveTopic($topic->id, $forum->id, 0, $forum->id);

        $this->assertTrue($result);
    }

    public function test_update_topic_locked_updates_locked_flag(): void
    {
        $topic = Topic::factory()->create(['locked' => false]);

        $this->repository->updateTopicLocked($topic->id, true);

        $this->assertTrue((bool) Topic::query()->where('id', $topic->id)->value('locked'));
    }

    public function test_update_topic_sticky_updates_sticky_value(): void
    {
        $topic = Topic::factory()->create(['sticky' => false]);

        $this->repository->updateTopicSticky($topic->id, true);

        $this->assertTrue((bool) Topic::query()->where('id', $topic->id)->value('sticky'));
    }

    public function test_update_topic_highlight_updates_hlcolor(): void
    {
        $topic = Topic::factory()->create(['hlcolor' => 0]);

        $this->repository->updateTopicHighlight($topic->id, 5);

        $this->assertSame(5, (int) Topic::query()->where('id', $topic->id)->value('hlcolor'));
    }
}
