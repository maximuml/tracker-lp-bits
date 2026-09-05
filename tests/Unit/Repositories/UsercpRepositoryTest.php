<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Repositories\UsercpRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for UsercpRepository.
 *
 * Covers getUserById(), updateUser(), updateLastOffer(), emailExistsForOther(),
 * getCommentCount(), getForumPostCount(), getTotalPostCount(),
 * getTopicPostCount(), getStylesheetOptions(), getCountryOptions().
 */
final class UsercpRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UsercpRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UsercpRepository;
    }

    public function test_get_user_by_id_returns_user(): void
    {
        $user = User::factory()->create();

        $found = $this->repository->getUserById($user->id);

        $this->assertSame($user->id, $found->id);
        $this->assertSame($user->username, $found->username);
    }

    public function test_get_user_by_id_throws_for_nonexistent(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->getUserById(999999);
    }

    public function test_update_user_modifies_fields(): void
    {
        $user = User::factory()->create(['title' => '']);

        $result = $this->repository->updateUser($user->id, ['title' => 'New Title']);

        $this->assertTrue($result);
        $this->assertSame('New Title', User::query()->where('id', $user->id)->value('title'));
    }

    public function test_update_last_offer_sets_timestamp(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->updateLastOffer($user->id);

        $this->assertTrue($result);
        $lastOffer = User::query()->where('id', $user->id)->value('last_offer');
        $this->assertNotNull($lastOffer);
    }

    public function test_email_exists_for_other_returns_true_when_email_used_by_different_user(): void
    {
        $user1 = User::factory()->create(['email' => 'shared@example.com']);
        $user2 = User::factory()->create();

        $result = $this->repository->emailExistsForOther('shared@example.com', $user2->id);

        $this->assertTrue($result);
    }

    public function test_email_exists_for_other_returns_false_when_email_used_by_same_user(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $result = $this->repository->emailExistsForOther('mine@example.com', $user->id);

        $this->assertFalse($result);
    }

    public function test_email_exists_for_other_returns_false_when_email_not_used(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->emailExistsForOther('unused@example.com', $user->id);

        $this->assertFalse($result);
    }

    public function test_get_comment_count_returns_count_for_user(): void
    {
        $user = User::factory()->create();
        Comment::query()->create([
            'user' => $user->id,
            'torrent' => 0,
            'added' => now()->toDateTimeString(),
            'text' => 'Test comment',
            'ori_text' => 'Test comment',
            'anonymous' => false,
        ]);

        $count = $this->repository->getCommentCount($user->id);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_get_comment_count_returns_zero_for_user_without_comments(): void
    {
        $user = User::factory()->create();

        $count = $this->repository->getCommentCount($user->id);

        $this->assertSame(0, $count);
    }

    public function test_get_forum_post_count_returns_count_for_user(): void
    {
        $user = User::factory()->create();
        $topicId = (int) DB::table('topics')->insertGetId([
            'userid' => $user->id,
            'subject' => 'Test topic',
            'forumid' => 1,
            'lastpost' => 0,
        ]);
        Post::query()->create([
            'topicid' => $topicId,
            'userid' => $user->id,
            'added' => now()->toDateTimeString(),
            'body' => 'Test post',
            'ori_body' => 'Test post',
        ]);

        $count = $this->repository->getForumPostCount($user->id);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_get_total_post_count_returns_all_posts(): void
    {
        $initial = $this->repository->getTotalPostCount();

        $user = User::factory()->create();
        $topicId = (int) DB::table('topics')->insertGetId([
            'userid' => $user->id,
            'subject' => 'Total count test',
            'forumid' => 1,
            'lastpost' => 0,
        ]);
        Post::query()->create([
            'topicid' => $topicId,
            'userid' => $user->id,
            'added' => now()->toDateTimeString(),
            'body' => 'Test post',
            'ori_body' => 'Test post',
        ]);

        $after = $this->repository->getTotalPostCount();

        $this->assertSame($initial + 1, $after);
    }

    public function test_get_topic_post_count_returns_count_for_topic(): void
    {
        $user = User::factory()->create();
        $topicId = (int) DB::table('topics')->insertGetId([
            'userid' => $user->id,
            'subject' => 'Topic count test',
            'forumid' => 1,
            'lastpost' => 0,
        ]);
        Post::query()->create([
            'topicid' => $topicId,
            'userid' => $user->id,
            'added' => now()->toDateTimeString(),
            'body' => 'Test post 1',
            'ori_body' => 'Test post 1',
        ]);
        Post::query()->create([
            'topicid' => $topicId,
            'userid' => $user->id,
            'added' => now()->toDateTimeString(),
            'body' => 'Test post 2',
            'ori_body' => 'Test post 2',
        ]);

        $count = $this->repository->getTopicPostCount($topicId);

        $this->assertSame(2, $count);
    }

    public function test_get_stylesheet_options_returns_array(): void
    {
        DB::table('stylesheets')->insert([
            'name' => 'TestTheme',
            'uri' => 'TestTheme',
        ]);

        $options = $this->repository->getStylesheetOptions();

        $this->assertIsArray($options);
        $this->assertNotEmpty($options);
        $this->assertArrayHasKey('TestTheme', $options);
    }

    public function test_get_country_options_returns_array(): void
    {
        DB::table('countries')->insert([
            'name' => 'TestCountry',
            'flagpic' => 'test.gif',
        ]);

        $options = $this->repository->getCountryOptions();

        $this->assertIsArray($options);
        $this->assertNotEmpty($options);
    }
}
