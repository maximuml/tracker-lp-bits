<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\PageLayoutRepository;
use App\Support\CurrentUser;
use App\Support\UserUpdateBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for PageLayoutRepository.
 *
 * Covers the DB-backed count/query methods (getInboxCount, getOutboxCount,
 * getConnectable, getActiveSeedCount, getActiveLeechCount,
 * getUnreadMessageCount, getUnreadNewsCount, getTotalReports,
 * getTotalCheaters, getTorrentApprovalNoneCount, getOpenComplaintsCount,
 * getOpenReportsCount, getOpenCheatersCount, getPendingInviteCount,
 * updateUser) and the early-return guards in prepareAccess() and
 * flushAccess().
 */
final class PageLayoutRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private PageLayoutRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable FK checks for the duration of the test — messages, peers,
        // and news have FK constraints to users/torrents but tests insert
        // with arbitrary IDs.  Use DELETE (DML) instead of TRUNCATE (DDL)
        // to avoid an implicit commit that would break DatabaseTransactions
        // rollback for subsequent tests.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('reports')->delete();
        DB::table('cheaters')->delete();
        DB::table('complains')->delete();
        DB::table('torrents')->delete();
        DB::table('invites')->delete();
        DB::table('news')->delete();
        DB::table('messages')->delete();
        DB::table('peers')->delete();
        DB::table('users')->delete();
        $this->repository = new PageLayoutRepository;
    }

    protected function tearDown(): void
    {
        app(CurrentUser::class)->reset();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        parent::tearDown();
    }

    public function test_get_inbox_count_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getInboxCount(1));
    }

    public function test_get_inbox_count_counts_messages_with_nonzero_location(): void
    {
        DB::table('messages')->insert([
            ['sender' => 1, 'receiver' => 10, 'added' => now()->toDateTimeString(), 'subject' => 'a', 'msg' => 'x', 'location' => 1, 'unread' => 0, 'saved' => 0],
            ['sender' => 2, 'receiver' => 10, 'added' => now()->toDateTimeString(), 'subject' => 'b', 'msg' => 'y', 'location' => 2, 'unread' => 0, 'saved' => 0],
            ['sender' => 3, 'receiver' => 10, 'added' => now()->toDateTimeString(), 'subject' => 'c', 'msg' => 'z', 'location' => 0, 'unread' => 0, 'saved' => 0],
        ]);

        $this->assertSame(2, $this->repository->getInboxCount(10));
    }

    public function test_get_outbox_count_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getOutboxCount(1));
    }

    public function test_get_outbox_count_counts_saved_messages(): void
    {
        DB::table('messages')->insert([
            ['sender' => 10, 'receiver' => 1, 'added' => now()->toDateTimeString(), 'subject' => 'a', 'msg' => 'x', 'location' => 1, 'unread' => 0, 'saved' => 1],
            ['sender' => 10, 'receiver' => 2, 'added' => now()->toDateTimeString(), 'subject' => 'b', 'msg' => 'y', 'location' => 1, 'unread' => 0, 'saved' => 0],
        ]);

        $this->assertSame(1, $this->repository->getOutboxCount(10));
    }

    public function test_get_connectable_returns_null_when_no_peers(): void
    {
        $this->assertNull($this->repository->getConnectable(1));
    }

    public function test_get_connectable_returns_latest_peer_value(): void
    {
        DB::table('peers')->insert([
            ['torrent' => 1, 'peer_id' => random_bytes(20), 'ip' => '1.1.1.1', 'userid' => 10, 'agent' => 'x', 'ipv4' => '1.1.1.1', 'ipv6' => '', 'port' => 1000, 'connectable' => 0, 'seeder' => 1, 'uploaded' => 0, 'downloaded' => 0, 'to_go' => 0, 'passkey' => str_repeat('a', 32), 'started' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString()],
            ['torrent' => 2, 'peer_id' => random_bytes(20), 'ip' => '1.1.1.1', 'userid' => 10, 'agent' => 'x', 'ipv4' => '1.1.1.1', 'ipv6' => '', 'port' => 1001, 'connectable' => 1, 'seeder' => 0, 'uploaded' => 0, 'downloaded' => 0, 'to_go' => 0, 'passkey' => str_repeat('b', 32), 'started' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString()],
        ]);

        // Ordered by id desc, so the second insert (connectable=1) is returned.
        $this->assertSame(1, $this->repository->getConnectable(10));
    }

    public function test_get_active_seed_count_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getActiveSeedCount(1));
    }

    public function test_get_active_seed_count_counts_seeders(): void
    {
        DB::table('peers')->insert([
            ['torrent' => 1, 'peer_id' => random_bytes(20), 'ip' => '1.1.1.1', 'userid' => 10, 'agent' => 'x', 'ipv4' => '1.1.1.1', 'ipv6' => '', 'port' => 1000, 'connectable' => 1, 'seeder' => 1, 'uploaded' => 0, 'downloaded' => 0, 'to_go' => 0, 'passkey' => str_repeat('a', 32), 'started' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString()],
            ['torrent' => 2, 'peer_id' => random_bytes(20), 'ip' => '1.1.1.1', 'userid' => 10, 'agent' => 'x', 'ipv4' => '1.1.1.1', 'ipv6' => '', 'port' => 1001, 'connectable' => 1, 'seeder' => 1, 'uploaded' => 0, 'downloaded' => 0, 'to_go' => 0, 'passkey' => str_repeat('b', 32), 'started' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString()],
            ['torrent' => 3, 'peer_id' => random_bytes(20), 'ip' => '1.1.1.1', 'userid' => 10, 'agent' => 'x', 'ipv4' => '1.1.1.1', 'ipv6' => '', 'port' => 1002, 'connectable' => 1, 'seeder' => 0, 'uploaded' => 0, 'downloaded' => 0, 'to_go' => 0, 'passkey' => str_repeat('c', 32), 'started' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString()],
        ]);

        $this->assertSame(2, $this->repository->getActiveSeedCount(10));
    }

    public function test_get_active_leech_count_counts_leechers(): void
    {
        DB::table('peers')->insert([
            ['torrent' => 1, 'peer_id' => random_bytes(20), 'ip' => '1.1.1.1', 'userid' => 10, 'agent' => 'x', 'ipv4' => '1.1.1.1', 'ipv6' => '', 'port' => 1000, 'connectable' => 1, 'seeder' => 0, 'uploaded' => 0, 'downloaded' => 0, 'to_go' => 0, 'passkey' => str_repeat('a', 32), 'started' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString()],
            ['torrent' => 2, 'peer_id' => random_bytes(20), 'ip' => '1.1.1.1', 'userid' => 10, 'agent' => 'x', 'ipv4' => '1.1.1.1', 'ipv6' => '', 'port' => 1001, 'connectable' => 1, 'seeder' => 1, 'uploaded' => 0, 'downloaded' => 0, 'to_go' => 0, 'passkey' => str_repeat('b', 32), 'started' => now()->toDateTimeString(), 'last_action' => now()->toDateTimeString()],
        ]);

        $this->assertSame(1, $this->repository->getActiveLeechCount(10));
    }

    public function test_get_unread_message_count_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getUnreadMessageCount(1));
    }

    public function test_get_unread_message_count_counts_unread(): void
    {
        DB::table('messages')->insert([
            ['sender' => 1, 'receiver' => 10, 'added' => now()->toDateTimeString(), 'subject' => 'a', 'msg' => 'x', 'location' => 1, 'unread' => 1, 'saved' => 0],
            ['sender' => 2, 'receiver' => 10, 'added' => now()->toDateTimeString(), 'subject' => 'b', 'msg' => 'y', 'location' => 1, 'unread' => 0, 'saved' => 0],
        ]);

        $this->assertSame(1, $this->repository->getUnreadMessageCount(10));
    }

    public function test_get_unread_news_count_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getUnreadNewsCount(null));
    }

    public function test_get_unread_news_count_counts_notify_news(): void
    {
        DB::table('news')->insert([
            ['userid' => 1, 'added' => now()->toDateTimeString(), 'title' => 'a', 'body' => 'x', 'notify' => 1],
            ['userid' => 1, 'added' => now()->toDateTimeString(), 'title' => 'b', 'body' => 'y', 'notify' => 0],
        ]);

        $this->assertSame(1, $this->repository->getUnreadNewsCount(null));
    }

    public function test_get_unread_news_count_filters_by_last_home(): void
    {
        DB::table('news')->insert([
            ['userid' => 1, 'added' => '2025-01-01 00:00:00', 'title' => 'old', 'body' => 'x', 'notify' => 1],
            ['userid' => 1, 'added' => '2025-06-01 00:00:00', 'title' => 'new', 'body' => 'y', 'notify' => 1],
        ]);

        $this->assertSame(1, $this->repository->getUnreadNewsCount('2025-03-01 00:00:00'));
    }

    public function test_get_unread_news_count_ignores_zero_last_home(): void
    {
        DB::table('news')->insert([
            ['userid' => 1, 'added' => '2025-01-01 00:00:00', 'title' => 'a', 'body' => 'x', 'notify' => 1],
        ]);

        $this->assertSame(1, $this->repository->getUnreadNewsCount('0000-00-00 00:00:00'));
    }

    public function test_get_total_reports_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getTotalReports());
    }

    public function test_get_total_reports_counts_all(): void
    {
        DB::table('reports')->insert([
            ['addedby' => 1, 'added' => now()->toDateTimeString(), 'type' => 'torrent', 'reason' => 'x', 'dealtwith' => 0],
            ['addedby' => 2, 'added' => now()->toDateTimeString(), 'type' => 'user', 'reason' => 'y', 'dealtwith' => 1],
        ]);

        $this->assertSame(2, $this->repository->getTotalReports());
    }

    public function test_get_total_cheaters_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getTotalCheaters());
    }

    public function test_get_total_cheaters_counts_all(): void
    {
        DB::table('cheaters')->insert([
            ['added' => now()->toDateTimeString(), 'userid' => 1, 'torrentid' => 1, 'uploaded' => 0, 'downloaded' => 0, 'anctime' => 0, 'seeders' => 0, 'leechers' => 0, 'hit' => 0, 'dealtwith' => 0, 'comment' => 'x'],
        ]);

        $this->assertSame(1, $this->repository->getTotalCheaters());
    }

    public function test_get_torrent_approval_none_count_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getTorrentApprovalNoneCount());
    }

    public function test_get_torrent_approval_none_count_counts_unapproved(): void
    {
        DB::table('torrents')->insert([
            ['name' => 'a', 'filename' => 'a.torrent', 'save_as' => 'a', 'category' => 1, 'size' => 1, 'type' => 'single', 'numfiles' => 1, 'owner' => 1, 'info_hash' => random_bytes(20), 'visible' => 1, 'banned' => 0, 'approval_status' => 0, 'added' => now()->toDateTimeString()],
            ['name' => 'b', 'filename' => 'b.torrent', 'save_as' => 'b', 'category' => 1, 'size' => 1, 'type' => 'single', 'numfiles' => 1, 'owner' => 1, 'info_hash' => random_bytes(20), 'visible' => 1, 'banned' => 0, 'approval_status' => 1, 'added' => now()->toDateTimeString()],
        ]);

        $this->assertSame(1, $this->repository->getTorrentApprovalNoneCount());
    }

    public function test_get_open_complaints_count_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getOpenComplaintsCount());
    }

    public function test_get_open_complaints_count_counts_unanswered(): void
    {
        DB::table('complains')->insert([
            ['uuid' => 'uuid-1', 'email' => 'a@x.com', 'body' => 'x', 'added' => now()->toDateTimeString(), 'answered' => 0, 'ip' => '1.1.1.1'],
            ['uuid' => 'uuid-2', 'email' => 'b@x.com', 'body' => 'y', 'added' => now()->toDateTimeString(), 'answered' => 1, 'ip' => '1.1.1.1'],
        ]);

        $this->assertSame(1, $this->repository->getOpenComplaintsCount());
    }

    public function test_get_open_reports_count_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getOpenReportsCount());
    }

    public function test_get_open_reports_count_counts_undealt(): void
    {
        DB::table('reports')->insert([
            ['addedby' => 1, 'added' => now()->toDateTimeString(), 'type' => 'torrent', 'reason' => 'x', 'dealtwith' => 0],
            ['addedby' => 2, 'added' => now()->toDateTimeString(), 'type' => 'user', 'reason' => 'y', 'dealtwith' => 1],
        ]);

        $this->assertSame(1, $this->repository->getOpenReportsCount());
    }

    public function test_get_open_cheaters_count_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getOpenCheatersCount());
    }

    public function test_get_open_cheaters_count_counts_undealt(): void
    {
        DB::table('cheaters')->insert([
            ['added' => now()->toDateTimeString(), 'userid' => 1, 'torrentid' => 1, 'uploaded' => 0, 'downloaded' => 0, 'anctime' => 0, 'seeders' => 0, 'leechers' => 0, 'hit' => 0, 'dealtwith' => 0, 'comment' => 'x'],
            ['added' => now()->toDateTimeString(), 'userid' => 2, 'torrentid' => 2, 'uploaded' => 0, 'downloaded' => 0, 'anctime' => 0, 'seeders' => 0, 'leechers' => 0, 'hit' => 0, 'dealtwith' => 1, 'comment' => 'y'],
        ]);

        $this->assertSame(1, $this->repository->getOpenCheatersCount());
    }

    public function test_get_pending_invite_count_returns_zero_when_none(): void
    {
        $this->assertSame(0, $this->repository->getPendingInviteCount(1));
    }

    public function test_get_pending_invite_count_counts_valid_pending_invites(): void
    {
        DB::table('invites')->insert([
            ['inviter' => 10, 'invitee' => '', 'hash' => str_repeat('a', 32), 'time_invited' => now()->toDateTimeString(), 'valid' => 1, 'expired_at' => now()->addDays(3)->toDateTimeString(), 'created_at' => now()->toDateTimeString()],
            ['inviter' => 10, 'invitee' => 'someone', 'hash' => str_repeat('b', 32), 'time_invited' => now()->toDateTimeString(), 'valid' => 1, 'expired_at' => now()->addDays(3)->toDateTimeString(), 'created_at' => now()->toDateTimeString()],
            ['inviter' => 10, 'invitee' => '', 'hash' => str_repeat('c', 32), 'time_invited' => now()->toDateTimeString(), 'valid' => 1, 'expired_at' => now()->subDays(1)->toDateTimeString(), 'created_at' => now()->toDateTimeString()],
        ]);

        $this->assertSame(1, $this->repository->getPendingInviteCount(10));
    }

    public function test_update_user_updates_columns(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->repository->updateUser($user->id, ['last_access' => '2025-01-01 12:00:00']);

        $row = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('2025-01-01 12:00:00', $row->last_access);
    }

    public function test_prepare_access_returns_early_when_no_user(): void
    {
        app(CurrentUser::class)->reset();

        $this->repository->prepareAccess();

        $this->expectNotToPerformAssertions();
    }

    public function test_flush_access_returns_early_when_no_user(): void
    {
        app(CurrentUser::class)->reset();

        $this->repository->flushAccess();

        $this->expectNotToPerformAssertions();
    }

    public function test_flush_access_returns_early_when_no_pending_updates(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        app(CurrentUser::class)->set(['id' => $user->id]);

        $originalAccess = DB::table('users')->where('id', $user->id)->value('last_access');

        $this->repository->flushAccess();

        // last_access should be unchanged since no updates were queued.
        $this->assertSame(
            $originalAccess,
            DB::table('users')->where('id', $user->id)->value('last_access')
        );
    }

    public function test_flush_access_writes_pending_updates_to_db(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        app(CurrentUser::class)->set(['id' => $user->id]);

        app(UserUpdateBatch::class)->add('last_access', '2025-06-15 10:00:00');

        $this->repository->flushAccess();

        $this->assertSame(
            '2025-06-15 10:00:00',
            DB::table('users')->where('id', $user->id)->value('last_access')
        );
    }
}
