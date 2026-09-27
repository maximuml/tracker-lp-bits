<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Models\Topic;
use App\Models\User;
use App\Repositories\TopicReadStateRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for TopicReadStateRepository.
 *
 * Covers getLastReadPosts(), markPostRead(), clearReadPosts().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TopicReadStateRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TopicReadStateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(TopicReadStateRepository::class);
    }

    public function test_mark_post_read_inserts_when_no_existing_row(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->create();

        $result = $this->repository->markPostRead($user->id, $topic->id, 500, 0);

        $this->assertTrue($result);
        $this->assertDatabaseHas('readposts', [
            'userid' => $user->id,
            'topicid' => $topic->id,
            'lastpostread' => 500,
        ]);
    }

    public function test_mark_post_read_updates_when_post_id_exceeds_catchup(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->create();
        DB::table('readposts')->insert(['userid' => $user->id, 'topicid' => $topic->id, 'lastpostread' => 400]);

        $result = $this->repository->markPostRead($user->id, $topic->id, 500, 450);

        $this->assertTrue($result);
        $this->assertSame(500, (int) DB::table('readposts')->where('userid', $user->id)->where('topicid', $topic->id)->value('lastpostread'));
    }

    public function test_mark_post_read_does_not_update_when_post_id_below_catchup(): void
    {
        $user = User::factory()->create();
        $topic = Topic::factory()->create();
        DB::table('readposts')->insert(['userid' => $user->id, 'topicid' => $topic->id, 'lastpostread' => 500]);

        $result = $this->repository->markPostRead($user->id, $topic->id, 400, 500);

        $this->assertTrue($result);
        $this->assertSame(500, (int) DB::table('readposts')->where('userid', $user->id)->where('topicid', $topic->id)->value('lastpostread'));
    }

    public function test_get_last_read_posts_returns_map_when_rows_exist(): void
    {
        $user = User::factory()->create();
        $topic1 = Topic::factory()->create();
        $topic2 = Topic::factory()->create();
        DB::table('readposts')->insert(['userid' => $user->id, 'topicid' => $topic1->id, 'lastpostread' => 500]);
        DB::table('readposts')->insert(['userid' => $user->id, 'topicid' => $topic2->id, 'lastpostread' => 600]);

        $result = $this->repository->getLastReadPosts($user->id);

        $this->assertNotNull($result);
        $this->assertSame(500, $result[$topic1->id]);
        $this->assertSame(600, $result[$topic2->id]);
    }

    public function test_get_last_read_posts_returns_null_when_no_rows(): void
    {
        $result = $this->repository->getLastReadPosts(999);

        $this->assertNull($result);
    }

    public function test_clear_read_posts_removes_all_rows_for_user(): void
    {
        $user = User::factory()->create();
        $topic1 = Topic::factory()->create();
        $topic2 = Topic::factory()->create();
        DB::table('readposts')->insert(['userid' => $user->id, 'topicid' => $topic1->id, 'lastpostread' => 500]);
        DB::table('readposts')->insert(['userid' => $user->id, 'topicid' => $topic2->id, 'lastpostread' => 600]);

        $this->repository->clearReadPosts($user->id);

        $this->assertDatabaseMissing('readposts', ['userid' => $user->id]);
    }
}
