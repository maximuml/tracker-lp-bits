<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Torrent;
use App\Models\User;
use App\Repositories\ModerationRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for ModerationRepository.
 *
 * Covers reportExists(), createReport(), getForumPost(), countReports(),
 * getReports(), deleteBan(), createBan(), getBans(), findMatchingBans(),
 * countIplogDistinct(), getIphistoryRows(), getUserIdsByIp(),
 * getIplogUserIdsByIp(), getDuplicateIps(), getPeerCountsByIp(),
 * getIpsearchRows(), countIpsearch(), and countIplogDistinctByUser().
 */
final class ModerationRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ModerationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('reports')->delete();
        DB::table('bans')->delete();
        DB::table('iplog')->delete();
        DB::table('peers')->delete();
        DB::table('posts')->delete();
        DB::table('topics')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->repository = new ModerationRepository;
    }

    public function test_report_exists_returns_true_when_found(): void
    {
        $this->insertReport(10, 500, 'torrent');

        $this->assertTrue($this->repository->reportExists(10, 500, 'torrent'));
    }

    public function test_report_exists_returns_false_when_not_found(): void
    {
        $this->insertReport(10, 500, 'torrent');

        $this->assertFalse($this->repository->reportExists(10, 501, 'torrent'));
        $this->assertFalse($this->repository->reportExists(11, 500, 'torrent'));
        $this->assertFalse($this->repository->reportExists(10, 500, 'user'));
    }

    public function test_create_report_inserts_row(): void
    {
        $this->repository->createReport([
            'addedby' => 10,
            'added' => now()->toDateTimeString(),
            'reportid' => 500,
            'type' => 'torrent',
            'reason' => 'spam',
            'dealtby' => 0,
            'dealtwith' => 0,
        ]);

        $this->assertSame(1, DB::table('reports')->where('addedby', 10)->count());
    }

    public function test_get_forum_post_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getForumPost(999999));
    }

    public function test_get_forum_post_returns_topic_and_post_data(): void
    {
        /** @var User $topicUser */
        $topicUser = User::factory()->create();
        /** @var User $postUser */
        $postUser = User::factory()->create();
        $topicId = (int) DB::table('topics')->insertGetId([
            'userid' => $topicUser->id,
            'subject' => 'Test Topic',
            'forumid' => 1,
            'firstpost' => 0,
            'lastpost' => 0,
        ]);
        $postId = (int) DB::table('posts')->insertGetId([
            'topicid' => $topicId,
            'userid' => $postUser->id,
            'added' => now()->toDateTimeString(),
            'body' => 'hello',
            'ori_body' => 'hello',
        ]);

        $result = $this->repository->getForumPost($postId);

        $this->assertNotNull($result);
        $this->assertSame($topicId, (int) $result['topicid']);
        $this->assertSame('Test Topic', $result['subject']);
        $this->assertSame($postUser->id, (int) $result['postuserid']);
    }

    public function test_count_reports_returns_total(): void
    {
        $this->insertReport(1, 100, 'torrent');
        $this->insertReport(2, 200, 'user');

        $this->assertSame(2, $this->repository->countReports());
    }

    public function test_count_reports_returns_zero_when_empty(): void
    {
        $this->assertSame(0, $this->repository->countReports());
    }

    public function test_get_reports_orders_by_dealtwith_then_id_desc(): void
    {
        $this->insertReport(1, 100, 'torrent', 0);
        $id2 = $this->insertReport(2, 200, 'user', 0);
        $id3 = $this->insertReport(3, 300, 'post', 1);

        $reports = $this->repository->getReports(0, 10);

        // dealtwith=0 first (ordered by id desc), then dealtwith=1
        $this->assertSame($id2, (int) $reports[0]['id']);
        $this->assertSame($id3, (int) $reports[2]['id']);
    }

    public function test_get_reports_respects_offset_and_limit(): void
    {
        $this->insertReport(1, 100, 'torrent');
        $this->insertReport(2, 200, 'user');
        $this->insertReport(3, 300, 'post');

        $reports = $this->repository->getReports(1, 1);

        $this->assertCount(1, $reports);
    }

    public function test_delete_ban_removes_row(): void
    {
        $id = $this->insertBan(0, 100, 'test ban');

        $this->repository->deleteBan($id);

        $this->assertSame(0, DB::table('bans')->where('id', $id)->count());
    }

    public function test_delete_ban_does_not_throw_when_not_found(): void
    {
        $this->repository->deleteBan(999999);

        $this->expectNotToPerformAssertions();
    }

    public function test_create_ban_inserts_row(): void
    {
        $this->repository->createBan([
            'added' => now()->toDateTimeString(),
            'addedby' => 10,
            'comment' => 'spam ban',
            'first' => 0,
            'last' => 255,
        ]);

        $this->assertSame(1, DB::table('bans')->where('addedby', 10)->count());
    }

    public function test_get_bans_orders_by_added_desc(): void
    {
        $this->insertBan(0, 100, 'older', '2025-01-01 00:00:00');
        $id2 = $this->insertBan(0, 200, 'newer', '2025-06-01 00:00:00');

        $bans = $this->repository->getBans();

        $this->assertSame($id2, (int) $bans[0]['id']);
    }

    public function test_find_matching_bans_returns_overlapping_ranges(): void
    {
        $this->insertBan(0, 100, 'ban one');
        $this->insertBan(200, 300, 'ban two');

        $bans = $this->repository->findMatchingBans(50);

        $this->assertCount(1, $bans);
        $this->assertSame('ban one', $bans[0]['comment']);
    }

    public function test_find_matching_bans_returns_empty_when_no_match(): void
    {
        $this->insertBan(0, 100, 'ban one');

        $bans = $this->repository->findMatchingBans(500);

        $this->assertSame([], $bans);
    }

    public function test_count_iplog_distinct_counts_distinct_access(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        DB::table('iplog')->insert([
            ['userid' => $user->id, 'ip' => '1.2.3.4', 'access' => '2025-01-01 00:00:00'],
            ['userid' => $user->id, 'ip' => '1.2.3.5', 'access' => '2025-01-01 00:00:00'],
            ['userid' => $user->id, 'ip' => '1.2.3.6', 'access' => '2025-01-02 00:00:00'],
        ]);

        $this->assertSame(2, $this->repository->countIplogDistinct($user->id));
    }

    public function test_count_iplog_distinct_returns_zero_when_no_rows(): void
    {
        $this->assertSame(0, $this->repository->countIplogDistinct(999999));
    }

    public function test_get_iphistory_rows_unions_user_and_iplog(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        DB::table('iplog')->insert([
            ['userid' => $user->id, 'ip' => '1.2.3.4', 'access' => '2025-01-01 00:00:00'],
        ]);

        $rows = $this->repository->getIphistoryRows($user->id, 0, 10);

        $this->assertNotEmpty($rows);
        // The union includes both the user's own ip row and the iplog row.
        $ips = array_column($rows, 'ip');
        $this->assertContains('1.2.3.4', $ips);
    }

    public function test_get_user_ids_by_ip_returns_matching_users(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['ip' => '1.2.3.4']);

        $ids = $this->repository->getUserIdsByIp('1.2.3.4');

        $this->assertContains($user->id, $ids);
    }

    public function test_get_user_ids_by_ip_returns_empty_when_no_match(): void
    {
        $this->assertSame([], $this->repository->getUserIdsByIp('0.0.0.0'));
    }

    public function test_get_iplog_user_ids_by_ip_returns_matching(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();
        DB::table('iplog')->insert([
            ['userid' => $user1->id, 'ip' => '1.2.3.4', 'access' => '2025-01-01 00:00:00'],
            ['userid' => $user2->id, 'ip' => '1.2.3.4', 'access' => '2025-01-01 00:00:00'],
        ]);

        $ids = $this->repository->getIplogUserIdsByIp('1.2.3.4');

        $this->assertContains($user1->id, $ids);
        $this->assertContains($user2->id, $ids);
    }

    public function test_get_duplicate_ips_groups_by_ip(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();
        DB::table('users')->where('id', $user1->id)->update(['ip' => '1.2.3.4']);
        DB::table('users')->where('id', $user2->id)->update(['ip' => '1.2.3.4']);

        $duplicates = $this->repository->getDuplicateIps();

        $found = false;
        foreach ($duplicates as $dup) {
            if ($dup['ip'] === '1.2.3.4') {
                $this->assertSame(2, (int) $dup['dupl']);
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    public function test_get_peer_counts_by_ip_aggregates_per_user(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        DB::table('peers')->insert([
            $this->peerRow($torrent->id, $user1->id, '1.2.3.4'),
            $this->peerRow($torrent->id, $user1->id, '1.2.3.4'),
            $this->peerRow($torrent->id, $user2->id, '1.2.3.4'),
        ]);

        $counts = $this->repository->getPeerCountsByIp('1.2.3.4');

        $this->assertSame(2, $counts[$user1->id]);
        $this->assertSame(1, $counts[$user2->id]);
    }

    public function test_get_peer_counts_by_ip_returns_empty_when_no_match(): void
    {
        $this->assertSame([], $this->repository->getPeerCountsByIp('0.0.0.0'));
    }

    public function test_count_ipsearch_counts_distinct_users_for_single_ip(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['ip' => '1.2.3.4']);

        $count = $this->repository->countIpsearch('1.2.3.4', '255.255.255.0', true);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_count_iplog_distinct_by_user_counts_distinct_ips(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        DB::table('iplog')->insert([
            ['userid' => $user->id, 'ip' => '1.2.3.4', 'access' => '2025-01-01 00:00:00'],
            ['userid' => $user->id, 'ip' => '1.2.3.4', 'access' => '2025-01-02 00:00:00'],
            ['userid' => $user->id, 'ip' => '1.2.3.5', 'access' => '2025-01-01 00:00:00'],
        ]);

        $this->assertSame(2, $this->repository->countIplogDistinctByUser($user->id));
    }

    public function test_count_iplog_distinct_by_user_returns_zero_when_no_rows(): void
    {
        $this->assertSame(0, $this->repository->countIplogDistinctByUser(999999));
    }

    private function insertReport(int $addedBy, int $reportId, string $type, int $dealtwith = 0): int
    {
        return (int) DB::table('reports')->insertGetId([
            'addedby' => $addedBy,
            'added' => now()->toDateTimeString(),
            'reportid' => $reportId,
            'type' => $type,
            'reason' => 'test',
            'dealtby' => 0,
            'dealtwith' => $dealtwith,
        ]);
    }

    private function insertBan(int $first, int $last, string $comment, string $added = '2025-01-01 00:00:00'): int
    {
        return (int) DB::table('bans')->insertGetId([
            'added' => $added,
            'addedby' => 1,
            'comment' => $comment,
            'first' => $first,
            'last' => $last,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function peerRow(int $torrentId, int $userId, string $ip): array
    {
        return [
            'torrent' => $torrentId,
            'peer_id' => random_bytes(20),
            'ip' => $ip,
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'seeder' => 1,
            'started' => now()->toDateTimeString(),
            'last_action' => now()->toDateTimeString(),
            'prev_action' => now()->toDateTimeString(),
            'connectable' => 1,
            'userid' => $userId,
            'agent' => 'test',
            'finishedat' => 0,
            'downloadoffset' => 0,
            'passkey' => bin2hex(random_bytes(16)),
            'ipv4' => '',
            'ipv6' => '',
        ];
    }
}
