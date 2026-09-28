<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Models\Forum;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\PostRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for PostRepository.
 *
 * Covers createPost(), updatePostBody(), deletePost(), countTopicPosts(),
 * getTopicPosts(), countUserPosts(), getTotalPostsCount(), getLastPostId(),
 * updateUserLastPost(), updateLastCatchup(), getForumTodayPostCount().
 * Shaped single-post reads are covered in PostLookupRepositoryTest.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class PostRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private PostRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(PostRepository::class);
    }

    public function test_create_post_returns_new_post_id(): void
    {
        $topic = Topic::factory()->create();
        $user = User::factory()->create();

        $postId = $this->repository->createPost($topic->id, $user->id, 'Test body', now()->toDateTimeString());

        $this->assertGreaterThan(0, $postId);
        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'topicid' => $topic->id,
            'userid' => $user->id,
            'body' => 'Test body',
        ]);
    }

    public function test_update_post_body_updates_body_and_edit_info(): void
    {
        $post = Post::factory()->create(['body' => 'Old body']);
        $editor = User::factory()->create();
        $date = now()->toDateTimeString();

        $this->repository->updatePostBody($post->id, 'New body', $date, $editor->id);

        $updated = Post::query()->where('id', $post->id)->first();
        $this->assertSame('New body', $updated->body);
        $this->assertSame($editor->id, (int) $updated->editedby);
    }

    public function test_delete_post_removes_post_and_decrements_forum_count(): void
    {
        $forum = Forum::factory()->create(['postcount' => 10]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id]);

        $this->repository->deletePost($post->id, $topic->id, $forum->id);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertSame(9, (int) Forum::query()->where('id', $forum->id)->value('postcount'));
    }

    public function test_count_topic_posts_returns_count(): void
    {
        $topic = Topic::factory()->create();
        Post::factory()->count(3)->create(['topicid' => $topic->id]);

        $count = $this->repository->countTopicPosts($topic->id);

        $this->assertSame(3, $count);
    }

    public function test_count_topic_posts_filters_by_author(): void
    {
        $topic = Topic::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        Post::factory()->count(2)->create(['topicid' => $topic->id, 'userid' => $user1->id]);
        Post::factory()->create(['topicid' => $topic->id, 'userid' => $user2->id]);

        $count = $this->repository->countTopicPosts($topic->id, $user1->id);

        $this->assertSame(2, $count);
    }

    public function test_get_topic_posts_returns_paginated_collection(): void
    {
        $topic = Topic::factory()->create();
        Post::factory()->count(5)->create(['topicid' => $topic->id]);

        $result = $this->repository->getTopicPosts($topic->id, null, 0, 3);

        $this->assertCount(3, $result);
    }

    public function test_count_user_posts_returns_count(): void
    {
        $user = User::factory()->create();
        Post::factory()->count(3)->create(['userid' => $user->id]);

        $count = $this->repository->countUserPosts($user->id);

        $this->assertSame(3, $count);
    }

    public function test_get_total_posts_count_returns_count(): void
    {
        Post::factory()->count(3)->create();

        $count = $this->repository->getTotalPostsCount();

        $this->assertGreaterThanOrEqual(3, $count);
    }

    public function test_get_last_post_id_returns_latest_post_id(): void
    {
        Post::factory()->create();
        $lastPost = Post::factory()->create();

        $result = $this->repository->getLastPostId();

        $this->assertSame($lastPost->id, $result);
    }

    public function test_update_user_last_post_updates_timestamp(): void
    {
        $user = User::factory()->create();
        $date = now()->toDateTimeString();

        $this->repository->updateUserLastPost($user->id, $date);

        $this->assertSame($date, (string) DB::table('users')->where('id', $user->id)->value('last_post'));
    }

    public function test_update_last_catchup_updates_value(): void
    {
        $user = User::factory()->create();

        $this->repository->updateLastCatchup($user->id, 500);

        $this->assertSame('500', (string) DB::table('users')->where('id', $user->id)->value('last_catchup'));
    }

    // --- forum search semantics (plan step 5) ---

    public function test_search_matches_raw_ampersand_not_html_escaped(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 0]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id, 'body' => 'R&B live set']);

        $this->assertSame(1, $this->repository->countForumSearchPosts('R&B', 0));
        $rows = $this->repository->searchForumPosts('R&B', 0, 0, 10);
        $this->assertSame($post->id, $rows->first()->id);
    }

    public function test_search_matches_literal_less_than_and_quotes(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 0]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        Post::factory()->create(['topicid' => $topic->id, 'body' => 'the tag <b> and "quotes" here']);

        $this->assertSame(1, $this->repository->countForumSearchPosts('<b>', 0));
        $this->assertSame(1, $this->repository->countForumSearchPosts('"quotes"', 0));
    }

    public function test_search_matches_cyrillic_body(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 0]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $post = Post::factory()->create(['topicid' => $topic->id, 'body' => 'Линкин Парк концерт']);

        $rows = $this->repository->searchForumPosts('Парк', 0, 0, 10);
        $this->assertSame($post->id, $rows->first()->id);
    }

    public function test_search_matches_first_post_via_topic_subject(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 0]);
        $topic = Topic::factory()->create(['forumid' => $forum->id, 'subject' => 'Hybrid Theory Remaster']);
        $post = Post::factory()->create(['topicid' => $topic->id, 'body' => 'unrelated body']);
        $topic->update(['firstpost' => $post->id]);

        $this->assertSame(1, $this->repository->countForumSearchPosts('Hybrid Theory', 0));
    }

    public function test_search_excludes_forums_above_min_class(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 100]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        Post::factory()->create(['topicid' => $topic->id, 'body' => 'secret staff talk']);

        $this->assertSame(0, $this->repository->countForumSearchPosts('secret', 10));
        $this->assertSame(0, $this->repository->searchForumPosts('secret', 10, 0, 10)->count());
    }

    public function test_search_honours_offset_and_per_page(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 0]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        $posts = Post::factory()->count(3)->sequence(
            ['body' => 'kw one'],
            ['body' => 'kw two'],
            ['body' => 'kw three'],
        )->create(['topicid' => $topic->id]);

        $page1 = $this->repository->searchForumPosts('kw', 0, 0, 2);
        $page2 = $this->repository->searchForumPosts('kw', 0, 2, 2);

        $this->assertSame(2, $page1->count());
        $this->assertSame(1, $page2->count());
        $this->assertSame($posts->last()->id, $page1->first()->id); // newest first
    }

    public function test_search_with_zero_per_page_runs_no_select(): void
    {
        $forum = Forum::factory()->create(['minclassread' => 0]);
        $topic = Topic::factory()->create(['forumid' => $forum->id]);
        Post::factory()->create(['topicid' => $topic->id, 'body' => 'kw body']);

        $selectQueries = 0;
        DB::listen(function ($query) use (&$selectQueries) {
            if (str_starts_with(ltrim((string) $query->sql), 'select') && ! str_contains($query->sql, 'count(')) {
                $selectQueries++;
            }
        });

        $rows = $this->repository->searchForumPosts('kw', 0, 0, 0);

        $this->assertSame(0, $rows->count());
        $this->assertSame(0, $selectQueries);
    }
}
