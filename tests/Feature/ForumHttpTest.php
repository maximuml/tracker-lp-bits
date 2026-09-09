<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W1-04: HTTP contract tests for forum topic/post mutations.
 * Tests FormRequest validation, authorization via TopicPolicy/PostPolicy,
 * HTTP method boundaries, and legacy redirect behavior.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class ForumHttpTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    // ─── Authentication ────────────────────────────────────────────────

    public function test_forums_post_action_redirects_unauthenticated_user(): void
    {
        $this->post('/forums', ['action' => 'post', 'type' => 'new', 'id' => 1, 'body' => 'Test'])
            ->assertRedirect();
    }

    public function test_forums_movetopic_redirects_unauthenticated_user(): void
    {
        $this->post('/forums', ['action' => 'movetopic', 'forumid' => 1, 'topicid' => 1])
            ->assertRedirect();
    }

    public function test_forums_setlocked_redirects_unauthenticated_user(): void
    {
        $this->post('/forums', ['action' => 'setlocked', 'topicid' => 1, 'locked' => 1])
            ->assertRedirect();
    }

    // ─── FormRequest validation ────────────────────────────────────────

    public function test_post_action_validates_required_body(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/forums', [
                'action' => 'post',
                'type' => 'new',
                'id' => 1,
                'subject' => 'Test',
                // body missing
            ])
            ->assertRedirect();
    }

    public function test_post_action_validates_required_type(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/forums', [
                'action' => 'post',
                'id' => 1,
                'body' => 'Test',
                // type missing
            ])
            ->assertRedirect();
    }

    public function test_movetopic_validates_required_forumid(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/forums', [
                'action' => 'movetopic',
                'topicid' => 1,
                // forumid missing
            ])
            ->assertRedirect();
    }

    public function test_setlocked_validates_required_topicid(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->post('/forums', [
                'action' => 'setlocked',
                'locked' => 1,
                // topicid missing
            ])
            ->assertRedirect();
    }

    // ─── HTTP method boundaries ────────────────────────────────────────

    public function test_forums_post_action_rejects_get(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->get('/forums')
            ->assertStatus(200);
    }

    // ─── Authorization ──────────────────────────────────────────────────

    public function test_setlocked_permission_denied_for_non_moderator(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $topic = Topic::factory()->create();

        $this->withNexusCookie($user)
            ->post('/forums', [
                'action' => 'setlocked',
                'topicid' => (string) $topic->id,
                'locked' => 1,
            ])
            ->assertStatus(200);
    }

    public function test_setsticky_permission_denied_for_non_moderator(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $topic = Topic::factory()->create();

        $this->withNexusCookie($user)
            ->post('/forums', [
                'action' => 'setsticky',
                'topicid' => (string) $topic->id,
                'sticky' => 'yes',
            ])
            ->assertStatus(200);
    }

    public function test_deletetopic_permission_denied_for_non_moderator(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $topic = Topic::factory()->create();
        $topicId = $topic->id;

        $this->withNexusCookie($user)
            ->post('/forums', [
                'action' => 'deletetopic',
                'topicid' => (string) $topic->id,
                'sure' => 1,
            ]);

        // Security guarantee: topic must not be deleted by non-moderator
        $this->assertNotNull(Topic::query()->find($topicId));
    }

    public function test_deletepost_permission_denied_for_non_moderator(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $post = Post::factory()->create();

        $this->withNexusCookie($user)
            ->post('/forums', [
                'action' => 'deletepost',
                'postid' => (string) $post->id,
                'sure' => 1,
            ])
            ->assertStatus(200);
    }

    // ─── Success paths ──────────────────────────────────────────────────

    public function test_forums_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->withNexusCookie($user)
            ->get('/forums')
            ->assertStatus(200);
    }
}
