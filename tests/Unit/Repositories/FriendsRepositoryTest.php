<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\FriendsRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for FriendsRepository.
 *
 * Covers getFriends(), getBlocks(), exists(), add(), and delete().
 */
final class FriendsRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private FriendsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable FK checks for the duration of the test — several tests
        // insert friends/blocks rows with arbitrary user IDs that do not
        // exist in the users table.  Use DELETE (DML) instead of TRUNCATE
        // (DDL) to avoid an implicit commit that would break
        // DatabaseTransactions rollback for subsequent tests.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('friends')->delete();
        DB::table('blocks')->delete();
        $this->repository = new FriendsRepository;
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        parent::tearDown();
    }

    public function test_get_friends_returns_empty_array_when_none(): void
    {
        $this->assertSame([], $this->repository->getFriends(123));
    }

    public function test_get_friends_returns_joined_user_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $friend */
        $friend = User::factory()->create();

        DB::table('friends')->insert([
            'userid' => $user->id,
            'friendid' => $friend->id,
        ]);

        $result = $this->repository->getFriends($user->id);

        $this->assertCount(1, $result);
        $this->assertSame($friend->id, (int) $result[0]['id']);
        $this->assertArrayHasKey('class', $result[0]);
        $this->assertArrayHasKey('avatar', $result[0]);
    }

    public function test_get_friends_orders_by_id(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $friend1 */
        $friend1 = User::factory()->create();
        /** @var User $friend2 */
        $friend2 = User::factory()->create();

        // Insert out of order to verify ordering.
        DB::table('friends')->insert([
            ['userid' => $user->id, 'friendid' => $friend2->id],
            ['userid' => $user->id, 'friendid' => $friend1->id],
        ]);

        $result = $this->repository->getFriends($user->id);

        $this->assertSame($friend1->id, (int) $result[0]['id']);
        $this->assertSame($friend2->id, (int) $result[1]['id']);
    }

    public function test_get_blocks_returns_empty_array_when_none(): void
    {
        $this->assertSame([], $this->repository->getBlocks(123));
    }

    public function test_get_blocks_returns_block_ids(): void
    {
        DB::table('blocks')->insert([
            ['userid' => 10, 'blockid' => 20],
            ['userid' => 10, 'blockid' => 30],
            ['userid' => 99, 'blockid' => 20],
        ]);

        $result = $this->repository->getBlocks(10);

        $this->assertCount(2, $result);
        $this->assertSame(20, (int) $result[0]['id']);
        $this->assertSame(30, (int) $result[1]['id']);
    }

    public function test_exists_returns_false_for_nonexistent_friend(): void
    {
        $this->assertFalse($this->repository->exists(1, 'friend', 2));
    }

    public function test_exists_returns_true_for_existing_friend(): void
    {
        DB::table('friends')->insert([
            'userid' => 1,
            'friendid' => 2,
        ]);

        $this->assertTrue($this->repository->exists(1, 'friend', 2));
    }

    public function test_exists_returns_true_for_existing_block(): void
    {
        DB::table('blocks')->insert([
            'userid' => 1,
            'blockid' => 3,
        ]);

        $this->assertTrue($this->repository->exists(1, 'block', 3));
    }

    public function test_exists_only_checks_given_user(): void
    {
        DB::table('friends')->insert([
            'userid' => 5,
            'friendid' => 2,
        ]);

        $this->assertFalse($this->repository->exists(1, 'friend', 2));
    }

    public function test_add_inserts_friend_record(): void
    {
        $this->repository->add(1, 'friend', 2);

        $this->assertTrue(DB::table('friends')->where('userid', 1)->where('friendid', 2)->exists());
        $this->assertSame(0, DB::table('blocks')->count());
    }

    public function test_add_inserts_block_record(): void
    {
        $this->repository->add(1, 'block', 3);

        $this->assertTrue(DB::table('blocks')->where('userid', 1)->where('blockid', 3)->exists());
        $this->assertSame(0, DB::table('friends')->count());
    }

    public function test_delete_removes_friend_and_returns_count(): void
    {
        DB::table('friends')->insert([
            'userid' => 1,
            'friendid' => 2,
        ]);

        $count = $this->repository->delete(1, 'friend', 2);

        $this->assertSame(1, $count);
        $this->assertFalse(DB::table('friends')->where('userid', 1)->where('friendid', 2)->exists());
    }

    public function test_delete_removes_block_and_returns_count(): void
    {
        DB::table('blocks')->insert([
            'userid' => 1,
            'blockid' => 3,
        ]);

        $count = $this->repository->delete(1, 'block', 3);

        $this->assertSame(1, $count);
        $this->assertFalse(DB::table('blocks')->where('userid', 1)->where('blockid', 3)->exists());
    }

    public function test_delete_returns_zero_when_not_found(): void
    {
        $count = $this->repository->delete(1, 'friend', 999);

        $this->assertSame(0, $count);
    }
}
