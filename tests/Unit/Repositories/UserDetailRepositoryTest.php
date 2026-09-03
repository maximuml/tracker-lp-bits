<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\BonusLogs;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\UserModifyLog;
use App\Repositories\UserDetailRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for UserDetailRepository.
 *
 * Covers getUser(), isFriend(), isBlocked(), getIplogCount(), getPeers(),
 * getTrueTraffic(), getWarnedBy(), getUserWithMedals(), getCommentCount(),
 * getPostCount(), getTemporaryInviteCount(), getModComment(), and
 * getBonusComment().
 */
final class UserDetailRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UserDetailRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable FK checks for the duration of the test — several tests
        // insert peers/snatched/comments/posts rows with arbitrary IDs that
        // do not exist in the referenced tables.  Use DELETE (DML) instead
        // of TRUNCATE (DDL) to avoid an implicit commit that would break
        // DatabaseTransactions rollback for subsequent tests.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('bonus_logs')->delete();
        DB::table('user_modify_logs')->delete();
        DB::table('posts')->delete();
        DB::table('comments')->delete();
        DB::table('snatched')->delete();
        DB::table('peers')->delete();
        DB::table('iplog')->delete();
        DB::table('blocks')->delete();
        DB::table('friends')->delete();
        DB::table('users')->delete();
        $this->repository = new UserDetailRepository;
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        parent::tearDown();
    }

    public function test_get_user_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getUser(999999));
    }

    public function test_get_user_returns_array_when_found(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->getUser($user->id);

        $this->assertNotNull($result);
        $this->assertSame($user->id, (int) $result['id']);
        $this->assertSame($user->username, $result['username']);
    }

    public function test_is_friend_returns_false_when_not_friend(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertFalse($this->repository->isFriend($user->id, 888));
    }

    public function test_is_friend_returns_true_when_friend_exists(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $friend */
        $friend = User::factory()->create();

        DB::table('friends')->insert([
            'userid' => $user->id,
            'friendid' => $friend->id,
        ]);

        $this->assertTrue($this->repository->isFriend($user->id, $friend->id));
    }

    public function test_is_blocked_returns_false_when_not_blocked(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertFalse($this->repository->isBlocked($user->id, 777));
    }

    public function test_is_blocked_returns_true_when_block_exists(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $blocked */
        $blocked = User::factory()->create();

        DB::table('blocks')->insert([
            'userid' => $user->id,
            'blockid' => $blocked->id,
        ]);

        $this->assertTrue($this->repository->isBlocked($user->id, $blocked->id));
    }

    public function test_get_iplog_count_returns_zero_when_none(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertSame(0, $this->repository->getIplogCount($user->id));
    }

    public function test_get_iplog_count_counts_distinct_ips(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        DB::table('iplog')->insert([
            ['ip' => '1.1.1.1', 'userid' => $user->id, 'access' => now()->toDateTimeString()],
            ['ip' => '1.1.1.1', 'userid' => $user->id, 'access' => now()->toDateTimeString()],
            ['ip' => '2.2.2.2', 'userid' => $user->id, 'access' => now()->toDateTimeString()],
        ]);

        $this->assertSame(2, $this->repository->getIplogCount($user->id));
    }

    public function test_get_peers_returns_empty_array_when_none(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertSame([], $this->repository->getPeers($user->id));
    }

    public function test_get_peers_returns_grouped_rows(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        DB::table('peers')->insert([
            [
                'torrent' => 1, 'peer_id' => random_bytes(20), 'ip' => '1.1.1.1',
                'userid' => $user->id, 'agent' => 'uTorrent', 'ipv4' => '1.1.1.1',
                'ipv6' => '', 'port' => 5000, 'connectable' => 1, 'seeder' => 1,
                'uploaded' => 0, 'downloaded' => 0, 'to_go' => 0, 'passkey' => str_repeat('a', 32),
                'started' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString(),
            ],
            [
                'torrent' => 2, 'peer_id' => random_bytes(20), 'ip' => '1.1.1.1',
                'userid' => $user->id, 'agent' => 'uTorrent', 'ipv4' => '1.1.1.1',
                'ipv6' => '', 'port' => 5000, 'connectable' => 1, 'seeder' => 0,
                'uploaded' => 0, 'downloaded' => 0, 'to_go' => 0, 'passkey' => str_repeat('b', 32),
                'started' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString(),
            ],
        ]);

        $result = $this->repository->getPeers($user->id);

        // Same agent/ipv4/ipv6/port grouped into one row.
        $this->assertCount(1, $result);
        $this->assertSame('uTorrent', $result[0]['agent']);
    }

    public function test_get_true_traffic_returns_zeros_when_none(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->getTrueTraffic($user->id);

        $this->assertSame(['uploaded' => 0, 'downloaded' => 0], $result);
    }

    public function test_get_true_traffic_sums_uploaded_and_downloaded(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        DB::table('snatched')->insert([
            ['torrentid' => 1, 'userid' => $user->id, 'uploaded' => 1000, 'downloaded' => 500, 'ip' => '1.1.1.1', 'port' => 5000, 'seedtime' => 0, 'leechtime' => 0, 'startdat' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString(), 'finished' => 0],
            ['torrentid' => 2, 'userid' => $user->id, 'uploaded' => 2000, 'downloaded' => 1000, 'ip' => '1.1.1.1', 'port' => 5001, 'seedtime' => 0, 'leechtime' => 0, 'startdat' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString(), 'finished' => 0],
        ]);

        $result = $this->repository->getTrueTraffic($user->id);

        $this->assertSame(3000, $result['uploaded']);
        $this->assertSame(1500, $result['downloaded']);
    }

    public function test_get_warned_by_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getWarnedBy(999999));
    }

    public function test_get_warned_by_returns_id_and_username_when_found(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->getWarnedBy($user->id);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result['id']);
        $this->assertSame($user->username, $result['username']);
    }

    public function test_get_user_with_medals_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getUserWithMedals(999999));
    }

    public function test_get_user_with_medals_returns_user_when_found(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->getUserWithMedals($user->id);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
        $this->assertTrue($result->relationLoaded('valid_medals'));
    }

    public function test_get_comment_count_returns_zero_when_none(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertSame(0, $this->repository->getCommentCount($user->id));
    }

    public function test_get_comment_count_counts_comments_for_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Comment::query()->insert([
            ['user' => $user->id, 'torrent' => 1, 'added' => now()->toDateTimeString(), 'text' => 'a', 'ori_text' => 'a'],
            ['user' => $user->id, 'torrent' => 2, 'added' => now()->toDateTimeString(), 'text' => 'b', 'ori_text' => 'b'],
            ['user' => 999, 'torrent' => 1, 'added' => now()->toDateTimeString(), 'text' => 'c', 'ori_text' => 'c'],
        ]);

        $this->assertSame(2, $this->repository->getCommentCount($user->id));
    }

    public function test_get_post_count_returns_zero_when_none(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertSame(0, $this->repository->getPostCount($user->id));
    }

    public function test_get_post_count_counts_posts_for_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Post::query()->insert([
            ['topicid' => 1, 'userid' => $user->id, 'added' => now()->toDateTimeString(), 'body' => 'a', 'ori_body' => 'a'],
            ['topicid' => 2, 'userid' => $user->id, 'added' => now()->toDateTimeString(), 'body' => 'b', 'ori_body' => 'b'],
            ['topicid' => 1, 'userid' => 999, 'added' => now()->toDateTimeString(), 'body' => 'c', 'ori_body' => 'c'],
        ]);

        $this->assertSame(2, $this->repository->getPostCount($user->id));
    }

    public function test_get_temporary_invite_count_returns_zero_when_none(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertSame(0, $this->repository->getTemporaryInviteCount($user));
    }

    public function test_get_temporary_invite_count_counts_valid_invites(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        DB::table('invites')->insert([
            ['inviter' => $user->id, 'invitee' => '', 'hash' => str_repeat('a', 32), 'time_invited' => now()->toDateTimeString(), 'valid' => 1, 'expired_at' => now()->addDays(3)->toDateTimeString(), 'created_at' => now()->toDateTimeString()],
            ['inviter' => $user->id, 'invitee' => '', 'hash' => str_repeat('b', 32), 'time_invited' => now()->toDateTimeString(), 'valid' => 1, 'expired_at' => now()->addDays(5)->toDateTimeString(), 'created_at' => now()->toDateTimeString()],
            ['inviter' => $user->id, 'invitee' => 'someone', 'hash' => str_repeat('c', 32), 'time_invited' => now()->toDateTimeString(), 'valid' => 1, 'expired_at' => now()->addDays(3)->toDateTimeString(), 'created_at' => now()->toDateTimeString()],
        ]);

        $this->assertSame(2, $this->repository->getTemporaryInviteCount($user));
    }

    public function test_get_mod_comment_returns_empty_string_when_none(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertSame('', $this->repository->getModComment($user->id));
    }

    public function test_get_mod_comment_returns_formatted_logs_limited_to_20(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        for ($i = 1; $i <= 25; $i++) {
            UserModifyLog::query()->create([
                'user_id' => $user->id,
                'content' => "log entry {$i}",
            ]);
        }

        $result = $this->repository->getModComment($user->id);

        // Only the latest 20 entries are included (ordered desc).
        $lines = explode("\n", $result);
        $this->assertCount(20, $lines);
        $this->assertStringContainsString('log entry 25', $lines[0]);
        $this->assertStringContainsString('log entry 6', $lines[19]);
    }

    public function test_get_bonus_comment_returns_empty_string_when_none(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertSame('', $this->repository->getBonusComment($user->id));
    }

    public function test_get_bonus_comment_returns_formatted_logs_limited_to_20(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        for ($i = 1; $i <= 22; $i++) {
            BonusLogs::query()->create([
                'uid' => $user->id,
                'business_type' => 1,
                'old_total_value' => 0,
                'value' => 10,
                'new_total_value' => 10,
                'comment' => "bonus entry {$i}",
            ]);
        }

        $result = $this->repository->getBonusComment($user->id);

        $lines = explode("\n", $result);
        $this->assertCount(20, $lines);
        $this->assertStringContainsString('bonus entry 22', $lines[0]);
        $this->assertStringContainsString('bonus entry 3', $lines[19]);
    }
}
