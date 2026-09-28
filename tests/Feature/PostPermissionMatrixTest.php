<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission\RoutePermissionEnum;
use App\Enums\UserClass;
use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Posts API permission matrix (modernization plan step 4).
 *
 * | Scenario          | Sanctum ability | Domain check                        | Status |
 * |-------------------|-----------------|-------------------------------------|--------|
 * | read post         | TOPIC_LIST      | class >= forum.minclassread         | 200    |
 * | reply (store)     | TOPIC_LIST      | class >= forum.minclasswrite,       | 200    |
 * |                   |                 | unlocked topic or forum moderator   |        |
 * | edit/delete own   | TOPIC_LIST      | post.userid = user + minclasswrite; | 200    |
 * |                   |                 | delete rejects topic's first post   |        |
 * | moderate others'  | TOPIC_LIST      | forummods row or POST_MANAGE        | 200    |
 *
 * Domain denials surface as 422 (ValidationException), auth/ability
 * failures as 401/403 — the two layers are intentionally distinct.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::MUTATION)]
final class PostPermissionMatrixTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function actAs(User $user, array $abilities = [RoutePermissionEnum::TOPIC_LIST->value]): User
    {
        Sanctum::actingAs($user, $abilities);

        return $user;
    }

    private function makeModerator(User $user, Forum $forum): void
    {
        DB::table('forummods')->insert([
            'forumid' => $forum->id,
            'userid' => $user->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // Scenario 1: read post
    // -----------------------------------------------------------------------

    public function test_read_allows_member_with_topic_list_and_readable_forum(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id]);

        $this->getJson("/api/v1/topics/{$topic->id}/posts/{$post->id}")->assertOk();
    }

    public function test_read_denies_guest_with_401(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id]);

        $this->getJson("/api/v1/topics/{$topic->id}/posts/{$post->id}")->assertStatus(401);
    }

    public function test_read_denies_token_without_topic_list_with_403(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]), ['other-ability']);
        $forum = Forum::factory()->create(['minclassread' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id]);

        $this->getJson("/api/v1/topics/{$topic->id}/posts/{$post->id}")->assertStatus(403);
    }

    public function test_read_denies_class_below_minclassread(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 100]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id, 'body' => 'hidden']);

        $response = $this->getJson("/api/v1/topics/{$topic->id}/posts/{$post->id}");

        $response->assertStatus(422);
        $this->assertStringNotContainsString('hidden', (string) $response->getContent());
    }

    // -----------------------------------------------------------------------
    // Scenario 2: reply (store)
    // -----------------------------------------------------------------------

    public function test_reply_allows_member_with_writable_forum(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);

        $this->postJson("/api/v1/topics/{$topic->id}/posts", ['body' => 'hello'])
            ->assertOk();

        $this->assertSame(1, Post::query()->where('topicid', $topic->id)->count());
    }

    public function test_reply_denies_locked_topic_for_normal_member(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id, 'locked' => true]);

        $this->postJson("/api/v1/topics/{$topic->id}/posts", ['body' => 'hello'])
            ->assertStatus(422);

        $this->assertSame(0, Post::query()->where('topicid', $topic->id)->count());
    }

    public function test_reply_allows_forum_moderator_on_locked_topic(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id, 'locked' => true]);
        $this->makeModerator($user, $forum);

        $this->postJson("/api/v1/topics/{$topic->id}/posts", ['body' => 'mod reply'])
            ->assertOk();

        $this->assertSame(1, Post::query()->where('topicid', $topic->id)->count());
    }

    public function test_reply_denies_class_below_minclasswrite(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 100]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);

        $this->postJson("/api/v1/topics/{$topic->id}/posts", ['body' => 'hello'])
            ->assertStatus(422);

        $this->assertSame(0, Post::query()->where('topicid', $topic->id)->count());
    }

    // -----------------------------------------------------------------------
    // Scenario 3: edit / delete own post
    // -----------------------------------------------------------------------

    public function test_owner_can_edit_own_post(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id, 'userid' => $user->id, 'body' => 'ORIG']);

        $this->patchJson("/api/v1/topics/{$topic->id}/posts/{$post->id}", ['body' => 'NEW'])
            ->assertOk();

        $this->assertSame('NEW', $post->fresh()->body);
    }

    public function test_non_owner_cannot_edit_post(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id, 'body' => 'ORIG']);

        $this->patchJson("/api/v1/topics/{$topic->id}/posts/{$post->id}", ['body' => 'NEW'])
            ->assertStatus(422);

        $this->assertSame('ORIG', $post->fresh()->body);
    }

    public function test_owner_can_delete_own_non_first_post(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        Post::factory()->create(['topicid' => $topic->id]);
        $post = Post::factory()->create(['topicid' => $topic->id, 'userid' => $user->id]);

        $this->deleteJson("/api/v1/topics/{$topic->id}/posts/{$post->id}")
            ->assertOk();

        $this->assertNull(Post::query()->find($post->id));
    }

    public function test_owner_cannot_delete_first_post_of_topic(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id, 'userid' => $user->id]);

        $this->deleteJson("/api/v1/topics/{$topic->id}/posts/{$post->id}")
            ->assertStatus(422);

        $this->assertNotNull(Post::query()->find($post->id));
    }

    // -----------------------------------------------------------------------
    // Scenario 4: moderate others' posts
    // -----------------------------------------------------------------------

    public function test_forum_moderator_can_edit_others_post(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id, 'body' => 'ORIG']);
        $this->makeModerator($user, $forum);

        $this->patchJson("/api/v1/topics/{$topic->id}/posts/{$post->id}", ['body' => 'MOD'])
            ->assertOk();

        $this->assertSame('MOD', $post->fresh()->body);
    }

    public function test_forum_moderator_can_delete_others_post(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        Post::factory()->create(['topicid' => $topic->id]);
        $post = Post::factory()->create(['topicid' => $topic->id]);
        $this->makeModerator($user, $forum);

        $this->deleteJson("/api/v1/topics/{$topic->id}/posts/{$post->id}")
            ->assertOk();

        $this->assertNull(Post::query()->find($post->id));
    }

    public function test_non_owner_non_moderator_cannot_delete_post(): void
    {
        $user = $this->actAs(User::factory()->create(['class' => UserClass::USER->value]));
        $forum = Forum::factory()->create(['minclassread' => 1, 'minclasswrite' => 1]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        Post::factory()->create(['topicid' => $topic->id]);
        $post = Post::factory()->create(['topicid' => $topic->id]);

        $this->deleteJson("/api/v1/topics/{$topic->id}/posts/{$post->id}")
            ->assertStatus(422);

        $this->assertNotNull(Post::query()->find($post->id));
    }
}
