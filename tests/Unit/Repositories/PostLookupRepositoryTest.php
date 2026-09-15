<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\PostLookupRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for PostLookupRepository.
 *
 * Covers postExists(), getPost(), getPostWithUser(), getPreviousPostId(),
 * getPostForQuote(), getPostForEdit(), getPostWithTopic(), getPostEditInfo(),
 * getPostArrayById(), findPostArrayById(), getFirstPostId(),
 * getPostTopicAndUser().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class PostLookupRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private PostLookupRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(PostLookupRepository::class);
    }

    public function test_post_exists_returns_topic_id_when_found(): void
    {
        $topic = Topic::factory()->create();
        $post = Post::factory()->create(['topicid' => $topic->id]);

        $result = $this->repository->postExists($post->id);

        $this->assertSame($topic->id, $result);
    }

    public function test_post_exists_returns_null_when_not_found(): void
    {
        $result = $this->repository->postExists(999999);

        $this->assertNull($result);
    }

    public function test_get_post_returns_post_when_found(): void
    {
        $post = Post::factory()->create();

        $result = $this->repository->getPost($post->id);

        $this->assertNotNull($result);
        $this->assertSame($post->id, $result->id);
    }

    public function test_get_post_returns_null_when_not_found(): void
    {
        $result = $this->repository->getPost(999999);

        $this->assertNull($result);
    }

    public function test_get_post_with_user_eager_loads_user(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['userid' => $user->id]);

        $result = $this->repository->getPostWithUser($post->id);

        $this->assertNotNull($result);
        $this->assertTrue($result->relationLoaded('user'));
    }

    public function test_get_previous_post_id_returns_previous_post(): void
    {
        $topic = Topic::factory()->create();
        $post1 = Post::factory()->create(['topicid' => $topic->id]);
        $post2 = Post::factory()->create(['topicid' => $topic->id]);

        $result = $this->repository->getPreviousPostId($topic->id, $post2->id);

        $this->assertSame($post1->id, $result);
    }

    public function test_get_first_post_id_returns_earliest_post(): void
    {
        $topic = Topic::factory()->create();
        $post1 = Post::factory()->create(['topicid' => $topic->id]);
        Post::factory()->create(['topicid' => $topic->id]);

        $result = $this->repository->getFirstPostId($topic->id);

        $this->assertSame($post1->id, $result);
    }

    public function test_get_post_for_quote_returns_post_data(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->create(['subject' => 'Test topic']);
        $post = Post::factory()->create(['topicid' => $topic->id, 'userid' => $user->id, 'body' => 'Quoted text']);

        $result = $this->repository->getPostForQuote($post->id);

        $this->assertNotNull($result);
        $this->assertSame('Quoted text', $result['body']);
        $this->assertSame('Test topic', $result['topic_subject']);
    }

    public function test_get_post_for_edit_returns_post_data(): void
    {
        $topic = Topic::factory()->create(['subject' => 'Edit topic']);
        $post = Post::factory()->create(['topicid' => $topic->id, 'body' => 'Editable text']);

        $result = $this->repository->getPostForEdit($post->id);

        $this->assertNotNull($result);
        $this->assertSame('Editable text', $result['body']);
        $this->assertTrue($result['is_first_post']);
    }

    public function test_get_post_with_topic_returns_post_and_topic_info(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->create(['locked' => false]);
        $post = Post::factory()->create(['topicid' => $topic->id, 'userid' => $user->id]);

        $result = $this->repository->getPostWithTopic($post->id);

        $this->assertNotNull($result);
        $this->assertSame($topic->id, $result['topicid']);
        $this->assertFalse((bool) $result['locked']);
    }

    public function test_get_post_edit_info_returns_topic_and_forum_info(): void
    {
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id]);

        $result = $this->repository->getPostEditInfo($post->id);

        $this->assertNotNull($result);
        $this->assertSame($topic->id, $result['topicid']);
        $this->assertSame($forum->id, $result['forumid']);
        $this->assertTrue($result['is_first_post']);
    }

    public function test_get_post_array_by_id_returns_array(): void
    {
        $post = Post::factory()->create(['body' => 'Array body']);

        $result = $this->repository->getPostArrayById($post->id);

        $this->assertSame('Array body', $result['body']);
    }

    public function test_find_post_array_by_id_returns_array_when_found(): void
    {
        $post = Post::factory()->create(['body' => 'Find body']);

        $result = $this->repository->findPostArrayById($post->id);

        $this->assertNotNull($result);
        $this->assertSame('Find body', $result['body']);
    }

    public function test_find_post_array_by_id_returns_null_when_not_found(): void
    {
        $result = $this->repository->findPostArrayById(999999);

        $this->assertNull($result);
    }

    public function test_get_post_topic_and_user_returns_array(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->create();
        $post = Post::factory()->create(['topicid' => $topic->id, 'userid' => $user->id]);

        $result = $this->repository->getPostTopicAndUser($post->id);

        $this->assertSame($topic->id, $result['topicid']);
        $this->assertSame($user->id, $result['userid']);
    }
}
