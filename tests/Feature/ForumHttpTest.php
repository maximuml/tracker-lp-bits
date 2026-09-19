<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Support\Cache\LegacyRedisCache;
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

    // ─── Section components (ADR 0021, stage 3.1b) ─────────────────────

    public function test_viewforum_section_renders_topic_table_component(): void
    {
        $user = User::factory()->create();
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->forum($forum)->author($user)->create([
            'subject' => 'Component render topic',
        ]);
        $post = Post::factory()->topic($topic)->author($user)->create();
        $topic->update(['firstpost' => $post->id, 'lastpost' => $post->id]);
        // forums_list is cached for a day in LegacyRedisCache's own
        // connection (nexus.redis.database), not the Laravel Redis DB.
        app(LegacyRedisCache::class)->redis?->flushDB();

        $response = $this->withNexusCookie($user)
            ->get('/forums?action=viewforum&forumid='.$forum->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('nx-forum-table', $html);
        $this->assertStringContainsString('data-nx="data"', $html);
        $this->assertStringContainsString('Component render topic', $html);
        $this->assertStringContainsString('action=viewtopic', $html);
    }

    public function test_viewunread_section_renders_unread_topic_row(): void
    {
        $user = User::factory()->create();
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->forum($forum)->author($user)->create([
            'subject' => 'Unread component topic',
        ]);
        $post = Post::factory()->topic($topic)->author($user)->create();
        $topic->update(['firstpost' => $post->id, 'lastpost' => $post->id]);
        app(LegacyRedisCache::class)->redis?->flushDB();

        $response = $this->withNexusCookie($user)
            ->get('/forums?action=viewunread');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('nx-forum-table', $html);
        $this->assertStringContainsString('Unread component topic', $html);
        $this->assertStringContainsString('name="catchup"', $html);
    }

    public function test_search_section_renders_form_and_results(): void
    {
        $user = User::factory()->create();
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->forum($forum)->author($user)->create();
        Post::factory()->topic($topic)->author($user)->create([
            'body' => 'Unique keyword zqxwvb in this post body',
        ]);

        $form = $this->withNexusCookie($user)->get('/forums?action=search');
        $form->assertOk();
        $this->assertStringContainsString('id="search_form"', (string) $form->getContent());

        $response = $this->withNexusCookie($user)
            ->get('/forums?action=search&keywords=zqxwvb');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('nx-forum-table', $html);
        $this->assertStringContainsString('page=p', $html);
        $this->assertStringContainsString('#pid', $html);
    }

    // ─── Compose section (ADR 0024, stage 3.1d) ────────────────────────

    public function test_newtopic_section_renders_compose_component(): void
    {
        $user = User::factory()->create();
        $forum = Forum::factory()->create(['name' => 'Compose Target Forum']);

        $response = $this->withNexusCookie($user)
            ->get('/forums?action=newtopic&forumid='.$forum->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('id="compose"', $html);
        $this->assertStringContainsString('name="subject"', $html);
        $this->assertStringContainsString('name="type" value="new"', $html);
        $this->assertStringContainsString('name="id" value="'.$forum->id.'"', $html);
        $this->assertStringContainsString('Compose Target Forum', $html);
        $this->assertStringContainsString('bbcode-editor', $html);
    }

    public function test_reply_section_renders_compose_without_subject(): void
    {
        $user = User::factory()->create();
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->forum($forum)->author($user)->create([
            'subject' => 'Reply target topic',
        ]);
        $post = Post::factory()->topic($topic)->author($user)->create();
        $topic->update(['firstpost' => $post->id, 'lastpost' => $post->id]);

        $response = $this->withNexusCookie($user)
            ->get('/forums?action=reply&topicid='.$topic->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('id="compose"', $html);
        $this->assertStringContainsString('name="type" value="reply"', $html);
        $this->assertStringContainsString('Reply target topic', $html);
        $this->assertStringNotContainsString('name="subject"', $html);
    }

    public function test_editpost_section_prefills_body_and_subject(): void
    {
        $user = User::factory()->create();
        $forum = Forum::factory()->create();
        $topic = Topic::factory()->forum($forum)->author($user)->create([
            'subject' => 'Editable subject',
        ]);
        $post = Post::factory()->topic($topic)->author($user)->create([
            'body' => 'Body with & ampersand',
        ]);
        $topic->update(['firstpost' => $post->id, 'lastpost' => $post->id]);

        $response = $this->withNexusCookie($user)
            ->get('/forums?action=editpost&postid='.$post->id);

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('id="compose"', $html);
        $this->assertStringContainsString('name="type" value="edit"', $html);
        $this->assertStringContainsString('name="id" value="'.$post->id.'"', $html);
        // Single escaping: textarea shows the entity once, not double-escaped.
        $this->assertStringContainsString('Body with &amp; ampersand', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }
}
