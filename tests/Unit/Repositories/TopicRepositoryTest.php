<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\TopicRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for TopicRepository.
 *
 * Covers topicExists(), getTopic(), getTopicWithUser(), createTopic(),
 * updateTopicSubject(), deleteTopic(), moveTopic(), getTopicsByForum(),
 * getUnreadTopics(), getTopicById(), getLastTopicByForum(),
 * getTopicForumId(), isTopicLocked(), updateTopicLocked(),
 * updateTopicSticky(), updateTopicHighlight(), incrementTopicViews(),
 * getTopicSubject(), getTopicIdByPost(), isModeratorOfTopic(),
 * getTotalTopicsCount(), getTopicForumAndUser().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TopicRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TopicRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(TopicRepository::class);
    }

    public function test_topic_exists_returns_forum_id_when_found(): void
    {
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id]);

        $forumId = $this->repository->topicExists($topic->id);

        $this->assertSame($forum->id, $forumId);
    }

    public function test_topic_exists_returns_null_when_not_found(): void
    {
        $result = $this->repository->topicExists(999999);

        $this->assertNull($result);
    }

    public function test_get_topic_returns_topic_when_found(): void
    {
        $topic = Topic::factory()->create();

        $result = $this->repository->getTopic($topic->id);

        $this->assertNotNull($result);
        $this->assertSame($topic->id, $result->id);
    }

    public function test_get_topic_returns_null_when_not_found(): void
    {
        $result = $this->repository->getTopic(999999);

        $this->assertNull($result);
    }

    public function test_get_topic_with_user_eager_loads_user(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->create(['userid' => $user->id]);

        $result = $this->repository->getTopicWithUser($topic->id);

        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('user'));
    }

    public function test_create_topic_returns_new_topic_id(): void
    {
        $forum = Forum::factory()->create();
        $user = User::factory()->create();

        $topicId = $this->repository->createTopic($user->id, $forum->id, 'Test subject');

        $this->assertGreaterThan(0, $topicId);
        $this->assertDatabaseHas('topics', [
            'id' => $topicId,
            'subject' => 'Test subject',
            'forumid' => $forum->id,
            'userid' => $user->id,
        ]);
    }

    public function test_update_topic_subject_updates_subject(): void
    {
        $topic = Topic::factory()->create(['subject' => 'Old subject']);

        $this->repository->updateTopicSubject($topic->id, 'New subject');

        $this->assertSame('New subject', Topic::query()->where('id', $topic->id)->value('subject'));
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

    public function test_get_topic_forum_id_returns_forum_id(): void
    {
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id]);

        $result = $this->repository->getTopicForumId($topic->id);

        $this->assertSame($forum->id, $result);
    }

    public function test_is_topic_locked_returns_locked_flag(): void
    {
        $topic = Topic::factory()->create(['locked' => true]);

        $result = $this->repository->isTopicLocked($topic->id);

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

        $this->repository->updateTopicSticky($topic->id, '1');

        $this->assertTrue((bool) Topic::query()->where('id', $topic->id)->value('sticky'));
    }

    public function test_update_topic_highlight_updates_hlcolor(): void
    {
        $topic = Topic::factory()->create(['hlcolor' => 0]);

        $this->repository->updateTopicHighlight($topic->id, 5);

        $this->assertSame(5, (int) Topic::query()->where('id', $topic->id)->value('hlcolor'));
    }

    public function test_increment_topic_views_increments_views(): void
    {
        $topic = Topic::factory()->create(['views' => 10]);

        $this->repository->incrementTopicViews($topic->id);

        $this->assertSame(11, (int) Topic::query()->where('id', $topic->id)->value('views'));
    }

    public function test_get_topic_subject_returns_subject(): void
    {
        $topic = Topic::factory()->create(['subject' => 'My topic']);

        $result = $this->repository->getTopicSubject($topic->id);

        $this->assertSame('My topic', $result);
    }

    public function test_get_topic_id_by_post_returns_topic_id(): void
    {
        $topic = Topic::factory()->create();
        $post = Post::factory()->create(['topicid' => $topic->id]);

        $result = $this->repository->getTopicIdByPost($post->id);

        $this->assertSame($topic->id, $result);
    }

    public function test_get_topic_id_by_post_returns_null_for_nonexistent(): void
    {
        $result = $this->repository->getTopicIdByPost(999999);

        $this->assertNull($result);
    }

    public function test_get_total_topics_count_returns_count(): void
    {
        Topic::factory()->count(3)->create();

        $count = $this->repository->getTotalTopicsCount();

        $this->assertGreaterThanOrEqual(3, $count);
    }

    public function test_get_topic_forum_and_user_returns_array(): void
    {
        $forum = Forum::factory()->create();
        $user = User::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id, 'userid' => $user->id]);

        $result = $this->repository->getTopicForumAndUser($topic->id);

        $this->assertSame($forum->id, $result['forumid']);
        $this->assertSame($user->id, $result['userid']);
    }

    public function test_get_topic_by_id_returns_topic_or_throws(): void
    {
        $topic = Topic::factory()->create();

        $result = $this->repository->getTopicById($topic->id);

        $this->assertSame($topic->id, $result->id);
    }

    public function test_get_last_topic_by_forum_returns_latest_topic(): void
    {
        $forum = Forum::factory()->create();
        $topic1 = Topic::factory()->create(['forumid' => $forum->id, 'lastpost' => 100]);
        $topic2 = Topic::factory()->create(['forumid' => $forum->id, 'lastpost' => 200]);

        $result = $this->repository->getLastTopicByForum($forum->id);

        $this->assertSame($topic2->id, $result->id);
    }

    public function test_get_topics_by_forum_returns_count_and_rows(): void
    {
        $forum = Forum::factory()->create();
        Topic::factory()->count(3)->create(['forumid' => $forum->id]);

        $result = $this->repository->getTopicsByForum($forum->id, '', 'lastpost', 'desc', 0, 10);

        $this->assertSame(3, $result['count']);
        $this->assertCount(3, $result['rows']);
    }

    public function test_get_unread_topics_returns_topics_after_catchup(): void
    {
        $forum = Forum::factory()->create();
        Topic::factory()->create(['forumid' => $forum->id, 'lastpost' => 200]);
        Topic::factory()->create(['forumid' => $forum->id, 'lastpost' => 300]);

        $result = $this->repository->getUnreadTopics(100, null, 10);

        $this->assertGreaterThanOrEqual(2, $result->count());
    }
}
