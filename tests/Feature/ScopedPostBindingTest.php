<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission\RoutePermissionEnum;
use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Nested posts must be scoped to their topic.
 *
 * topics/{topic}/posts/{post} must resolve to 404 when the post belongs
 * to another topic — before the substituted topic's permission checks
 * or any read/mutation can run on the wrong resource.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class ScopedPostBindingTest extends TestCase
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

    public function test_show_returns_404_for_post_from_another_topic(): void
    {
        $restricted = Forum::factory()->create(['minclassread' => 100]);
        $open = Forum::factory()->create(['minclassread' => 1]);
        $topicA = Topic::factory()->create(['forumid' => $restricted->id]);
        $topicB = Topic::factory()->create(['forumid' => $open->id]);
        $post = Post::factory()->create(['topicid' => $topicA->id, 'body' => 'restricted-content']);

        $this->getJson("/api/v1/topics/{$topicB->id}/posts/{$post->id}")
            ->assertNotFound();
    }

    public function test_show_matching_pair_still_works(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id, 'body' => 'visible-content']);

        $this->getJson("/api/v1/topics/{$topic->id}/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.data.body', 'visible-content');
    }

    public function test_update_returns_404_and_leaves_post_unchanged_for_another_topic(): void
    {
        $forumA = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $forumB = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topicA = Topic::factory()->create(['forumid' => $forumA->id]);
        $topicB = Topic::factory()->create(['forumid' => $forumB->id]);
        $post = Post::factory()->create([
            'topicid' => $topicA->id,
            'userid' => $this->user->id,
            'body' => 'ORIGINAL',
        ]);

        $this->patchJson("/api/v1/topics/{$topicB->id}/posts/{$post->id}", ['body' => 'CHANGED'])
            ->assertNotFound();

        $this->assertSame('ORIGINAL', $post->fresh()->body);
    }

    public function test_update_matching_pair_still_works(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create([
            'topicid' => $topic->id,
            'userid' => $this->user->id,
            'body' => 'ORIGINAL',
        ]);

        $this->patchJson("/api/v1/topics/{$topic->id}/posts/{$post->id}", ['body' => 'CHANGED'])
            ->assertOk();

        $this->assertSame('CHANGED', $post->fresh()->body);
    }

    public function test_destroy_returns_404_and_preserves_counters_for_another_topic(): void
    {
        $forumA = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1, 'postcount' => 2]);
        $forumB = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1, 'postcount' => 2]);
        $topicA = Topic::factory()->create(['forumid' => $forumA->id]);
        $topicB = Topic::factory()->create(['forumid' => $forumB->id]);
        $post = Post::factory()->create(['topicid' => $topicA->id, 'userid' => $this->user->id]);

        $this->deleteJson("/api/v1/topics/{$topicB->id}/posts/{$post->id}")
            ->assertNotFound();

        $this->assertNotNull($post->fresh());
        $this->assertSame(2, (int) $forumA->fresh()->postcount);
        $this->assertSame(2, (int) $forumB->fresh()->postcount);
    }

    public function test_destroy_matching_pair_still_works(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1, 'postcount' => 2]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        Post::factory()->create(['topicid' => $topic->id, 'userid' => $this->user->id]);
        $post = Post::factory()->create(['topicid' => $topic->id, 'userid' => $this->user->id]);

        $this->deleteJson("/api/v1/topics/{$topic->id}/posts/{$post->id}")
            ->assertOk();

        $this->assertNull($post->fresh());
        $this->assertSame(1, (int) $forum->fresh()->postcount);
    }
}
