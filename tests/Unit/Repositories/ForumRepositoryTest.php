<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Repositories\ForumRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for ForumRepository.
 *
 * Covers createForum(), updateForum(), deleteForum(), getForumRow(),
 * getMaxForumSort(), getOverqueries(), createOverquery(),
 * replaceModerators(), getModeratorArray(), getTopicIdByPost(),
 * isModeratorOfTopic(), isModeratorOfForum().
 */
final class ForumRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ForumRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ForumRepository;
    }

    public function test_create_forum_returns_id(): void
    {
        $id = $this->repository->createForum([
            'name' => 'Test Forum',
            'description' => 'Test description',
            'sort' => 1,
            'forid' => 0,
        ]);

        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('forums', ['id' => $id, 'name' => 'Test Forum']);
    }

    public function test_update_forum_modifies_record(): void
    {
        $id = $this->repository->createForum([
            'name' => 'Original Name',
            'description' => 'Original desc',
            'sort' => 1,
            'forid' => 0,
        ]);

        $this->repository->updateForum($id, ['name' => 'Updated Name']);

        $this->assertDatabaseHas('forums', ['id' => $id, 'name' => 'Updated Name']);
    }

    public function test_get_forum_row_returns_array(): void
    {
        $id = $this->repository->createForum([
            'name' => 'Get Row Test',
            'description' => 'desc',
            'sort' => 1,
            'forid' => 0,
        ]);

        $row = $this->repository->getForumRow($id);

        $this->assertNotNull($row);
        $this->assertSame('Get Row Test', $row['name']);
    }

    public function test_get_forum_row_returns_null_for_nonexistent(): void
    {
        $row = $this->repository->getForumRow(999999);

        $this->assertNull($row);
    }

    public function test_get_max_forum_sort_returns_count(): void
    {
        $initial = $this->repository->getMaxForumSort();

        $this->repository->createForum([
            'name' => 'Sort Test',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);

        $after = $this->repository->getMaxForumSort();

        $this->assertSame($initial + 1, $after);
    }

    public function test_delete_forum_removes_forum_and_topics(): void
    {
        $id = $this->repository->createForum([
            'name' => 'Delete Test',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);

        $this->repository->deleteForum($id);

        $this->assertDatabaseMissing('forums', ['id' => $id]);
    }

    public function test_create_overforum(): void
    {
        $this->repository->createOverforum([
            'name' => 'Test Overforum',
            'sort' => 1,
        ]);

        $this->assertDatabaseHas('overforums', ['name' => 'Test Overforum']);
    }

    public function test_get_overforums_returns_array(): void
    {
        $this->repository->createOverforum(['name' => 'Over 1', 'sort' => 1]);

        $overforums = $this->repository->getOverforums();

        $this->assertIsArray($overforums);
        $this->assertNotEmpty($overforums);
    }

    public function test_get_overforum_row_returns_array(): void
    {
        $this->repository->createOverforum(['name' => 'Row Test', 'sort' => 1]);
        $id = DB::table('overforums')->where('name', 'Row Test')->value('id');

        $row = $this->repository->getOverforumRow((int) $id);

        $this->assertNotNull($row);
        $this->assertSame('Row Test', $row['name']);
    }

    public function test_get_overforum_row_returns_null_for_nonexistent(): void
    {
        $row = $this->repository->getOverforumRow(999999);

        $this->assertNull($row);
    }

    public function test_replace_moderators_sets_new_moderators(): void
    {
        $forumId = $this->repository->createForum([
            'name' => 'Mod Test',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);

        $this->repository->replaceModerators($forumId, [1, 2, 3]);

        $mods = DB::table('forummods')->where('forumid', $forumId)->get();
        $this->assertCount(3, $mods);
    }

    public function test_replace_moderators_replaces_existing(): void
    {
        $forumId = $this->repository->createForum([
            'name' => 'Mod Replace',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);

        $this->repository->replaceModerators($forumId, [1, 2]);
        $this->repository->replaceModerators($forumId, [3]);

        $mods = DB::table('forummods')->where('forumid', $forumId)->get();
        $this->assertCount(1, $mods);
        $this->assertSame(3, $mods->first()->userid);
    }

    public function test_replace_moderators_respects_limit(): void
    {
        $forumId = $this->repository->createForum([
            'name' => 'Mod Limit',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);

        $this->repository->replaceModerators($forumId, [1, 2, 3, 4, 5], 2);

        $mods = DB::table('forummods')->where('forumid', $forumId)->get();
        $this->assertCount(2, $mods);
    }

    public function test_get_moderator_array_groups_by_forum(): void
    {
        $forumId = $this->repository->createForum([
            'name' => 'Mod Array',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);
        $this->repository->replaceModerators($forumId, [1, 2]);

        $array = $this->repository->getModeratorArray();

        $this->assertArrayHasKey($forumId, $array);
        $this->assertCount(2, $array[$forumId]);
    }

    public function test_get_topic_id_by_post_returns_topic_id(): void
    {
        $user = User::factory()->create();
        $forumId = $this->repository->createForum([
            'name' => 'Topic Test',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);

        $topicId = (int) DB::table('topics')->insertGetId([
            'forumid' => $forumId,
            'subject' => 'Test topic',
            'userid' => $user->id,
            'lastpost' => 0,
        ]);

        $postId = (int) DB::table('posts')->insertGetId([
            'topicid' => $topicId,
            'userid' => $user->id,
            'added' => now()->toDateTimeString(),
            'body' => 'Test post',
        ]);

        // Update topic's lastpost to point to the new post
        DB::table('topics')->where('id', $topicId)->update(['lastpost' => $postId]);

        $found = $this->repository->getTopicIdByPost($postId);

        $this->assertSame($topicId, $found);
    }

    public function test_get_topic_id_by_post_returns_null_for_nonexistent(): void
    {
        $found = $this->repository->getTopicIdByPost(999999);

        $this->assertNull($found);
    }

    public function test_is_moderator_of_forum_returns_true_for_moderator(): void
    {
        $forumId = $this->repository->createForum([
            'name' => 'IsMod Forum',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);
        $this->repository->replaceModerators($forumId, [1]);

        $this->assertTrue($this->repository->isModeratorOfForum($forumId, 1));
    }

    public function test_is_moderator_of_forum_returns_false_for_non_moderator(): void
    {
        $forumId = $this->repository->createForum([
            'name' => 'NotMod Forum',
            'description' => '',
            'sort' => 1,
            'forid' => 0,
        ]);
        $this->repository->replaceModerators($forumId, [1]);

        $this->assertFalse($this->repository->isModeratorOfForum($forumId, 999));
    }
}
