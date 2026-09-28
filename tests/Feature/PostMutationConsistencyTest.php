<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Repositories\ForumRepositoryInterface;
use App\Enums\Permission\RoutePermissionEnum;
use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Post create/update/delete must leave posts, topic pointers and forum
 * counters consistent: atomic multi-writes, no success on no-op deletes,
 * and a valid `lastpost` pointer after deletion.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class PostMutationConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->user = User::factory()->create(['class' => 10]);
        Sanctum::actingAs($this->user, [RoutePermissionEnum::TOPIC_LIST->value]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_store_rolls_back_post_when_a_later_write_fails(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1, 'postcount' => 0]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);

        $forumRepo = Mockery::mock(ForumRepositoryInterface::class);
        $forumRepo->shouldReceive('incrementForumPostCount')->once()
            ->andThrow(new RuntimeException('simulated failure'));
        app()->instance(ForumRepositoryInterface::class, $forumRepo);

        $this->postJson("/api/v1/topics/{$topic->id}/posts", ['body' => 'Hello']);

        $this->assertSame(0, Post::query()->where('topicid', $topic->id)->count());
        $this->assertSame(0, (int) $topic->fresh()->lastpost);
        $this->assertSame(0, (int) $forum->fresh()->postcount);
    }

    public function test_destroy_refuses_the_first_post_of_a_topic(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1, 'postcount' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id, 'userid' => $this->user->id]);
        $topic->update(['firstpost' => $post->id, 'lastpost' => $post->id]);

        $this->deleteJson("/api/v1/topics/{$topic->id}/posts/{$post->id}")
            ->assertUnprocessable();

        $this->assertNotNull($post->fresh());
        $this->assertSame(1, (int) $forum->fresh()->postcount);
    }

    public function test_destroy_last_post_recomputes_topic_lastpost(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1, 'postcount' => 2]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $first = Post::factory()->create(['topicid' => $topic->id, 'userid' => $this->user->id]);
        $last = Post::factory()->create(['topicid' => $topic->id, 'userid' => $this->user->id]);
        $topic->update(['firstpost' => $first->id, 'lastpost' => $last->id]);

        $this->deleteJson("/api/v1/topics/{$topic->id}/posts/{$last->id}")
            ->assertOk();

        $this->assertSame($first->id, (int) $topic->fresh()->lastpost);
        $this->assertSame(1, (int) $forum->fresh()->postcount);
    }

    public function test_repeated_destroy_is_not_successful_and_decrements_once(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1, 'postcount' => 2]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $first = Post::factory()->create(['topicid' => $topic->id, 'userid' => $this->user->id]);
        $last = Post::factory()->create(['topicid' => $topic->id, 'userid' => $this->user->id]);
        $topic->update(['firstpost' => $first->id, 'lastpost' => $last->id]);

        $this->deleteJson("/api/v1/topics/{$topic->id}/posts/{$last->id}")
            ->assertOk();
        $this->deleteJson("/api/v1/topics/{$topic->id}/posts/{$last->id}")
            ->assertNotFound();

        $this->assertSame(1, (int) $forum->fresh()->postcount);
    }
}
