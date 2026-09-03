<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\TorrentOperationAction;
use App\Models\Comment;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\TorrentTag;
use App\Repositories\TorrentDetailRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for TorrentDetailRepository.
 *
 * Covers getTorrent(), getMagicInfo(), getThanksInfo(), getCommentCount(),
 * getComments(), incrementViews(), getTagIds(), and
 * getLatestApprovalDenyLog().
 */
final class TorrentDetailRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentDetailRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        // Disable FK checks for the duration of the test — thanks, comments,
        // torrent_tags, and torrent_operation_logs have FK constraints to
        // users/tags/torrents but tests insert with arbitrary IDs.  Use
        // DELETE (DML) instead of TRUNCATE (DDL) to avoid an implicit commit
        // that would break DatabaseTransactions rollback for subsequent tests.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('torrent_operation_logs')->delete();
        DB::table('torrent_tags')->delete();
        DB::table('comments')->delete();
        DB::table('thanks')->delete();
        DB::table('magic')->delete();
        DB::table('torrent_extras')->delete();
        DB::table('torrents')->delete();
        DB::table('users')->delete();
        $this->repository = new TorrentDetailRepository;
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        parent::tearDown();
    }

    public function test_get_torrent_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getTorrent(999999));
    }

    public function test_get_torrent_returns_array_with_joined_names(): void
    {
        $catId = (int) DB::table('categories')->insertGetId(['name' => 'Movies', 'mode' => 7, 'sort_index' => 1]);
        $torrentId = $this->createTorrent(['category' => $catId]);

        $result = $this->repository->getTorrent($torrentId);

        $this->assertNotNull($result);
        $this->assertSame($torrentId, (int) $result['id']);
        $this->assertSame('Movies', $result['cat_name']);
        $this->assertSame(7, (int) $result['search_box_id']);
    }

    public function test_get_torrent_includes_torrent_extras_descr(): void
    {
        $torrentId = $this->createTorrent();
        DB::table('torrent_extras')->insert([
            'torrent_id' => $torrentId,
            'descr' => 'My description',
            'media_info' => 'tech info',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $result = $this->repository->getTorrent($torrentId);

        $this->assertNotNull($result);
        $this->assertSame('My description', $result['descr']);
        $this->assertSame('tech info', $result['technical_info']);
    }

    public function test_get_magic_info_returns_zeros_when_no_givers(): void
    {
        $torrentId = $this->createTorrent();

        $result = $this->repository->getMagicInfo($torrentId, 1);

        $this->assertSame(0, $result['sum_value']);
        $this->assertSame(0, $result['whether_have_give_value']);
        $this->assertSame('', $result['add_value']);
        $this->assertSame(0, $result['count_user_number']);
    }

    public function test_get_magic_info_sums_values_and_detects_current_user(): void
    {
        $torrentId = $this->createTorrent();

        DB::table('magic')->insert([
            ['torrentid' => $torrentId, 'userid' => 10, 'value' => 5, 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
            ['torrentid' => $torrentId, 'userid' => 20, 'value' => 3, 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
        ]);

        $result = $this->repository->getMagicInfo($torrentId, 10);

        $this->assertSame(8, $result['sum_value']);
        $this->assertSame(1, $result['whether_have_give_value']);
        $this->assertSame(5, (int) $result['add_value']);
        $this->assertSame(2, $result['count_user_number']);
    }

    public function test_get_magic_info_detects_current_user_not_in_givers(): void
    {
        $torrentId = $this->createTorrent();

        DB::table('magic')->insert([
            ['torrentid' => $torrentId, 'userid' => 10, 'value' => 5, 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
        ]);

        $result = $this->repository->getMagicInfo($torrentId, 99);

        $this->assertSame(5, $result['sum_value']);
        $this->assertSame(0, $result['whether_have_give_value']);
        $this->assertSame('', $result['add_value']);
    }

    public function test_get_thanks_info_returns_empty_when_none(): void
    {
        $torrentId = $this->createTorrent();

        $result = $this->repository->getThanksInfo($torrentId, 1);

        $this->assertSame(0, $result['count']);
        $this->assertFalse($result['has_thanked']);
    }

    public function test_get_thanks_info_detects_current_user_in_top_20(): void
    {
        $torrentId = $this->createTorrent();

        DB::table('thanks')->insert([
            ['torrentid' => $torrentId, 'userid' => 10],
            ['torrentid' => $torrentId, 'userid' => 20],
        ]);

        $result = $this->repository->getThanksInfo($torrentId, 10);

        $this->assertSame(2, $result['count']);
        $this->assertTrue($result['has_thanked']);
    }

    public function test_get_thanks_info_detects_current_user_beyond_top_20(): void
    {
        $torrentId = $this->createTorrent();

        // Insert 20 thanks first (filling the limit), then the target user.
        for ($i = 1; $i <= 20; $i++) {
            DB::table('thanks')->insert(['torrentid' => $torrentId, 'userid' => $i]);
        }
        DB::table('thanks')->insert(['torrentid' => $torrentId, 'userid' => 99]);

        $result = $this->repository->getThanksInfo($torrentId, 99);

        $this->assertSame(21, $result['count']);
        $this->assertTrue($result['has_thanked']);
    }

    public function test_get_thanks_info_returns_false_when_user_not_thanked(): void
    {
        $torrentId = $this->createTorrent();

        DB::table('thanks')->insert([
            ['torrentid' => $torrentId, 'userid' => 10],
        ]);

        $result = $this->repository->getThanksInfo($torrentId, 99);

        $this->assertSame(1, $result['count']);
        $this->assertFalse($result['has_thanked']);
    }

    public function test_get_comment_count_returns_zero_when_none(): void
    {
        $torrentId = $this->createTorrent();

        $this->assertSame(0, $this->repository->getCommentCount($torrentId));
    }

    public function test_get_comment_count_counts_comments_for_torrent(): void
    {
        $torrentId = $this->createTorrent();

        Comment::query()->insert([
            ['user' => 1, 'torrent' => $torrentId, 'added' => now()->toDateTimeString(), 'text' => 'a', 'ori_text' => 'a'],
            ['user' => 2, 'torrent' => $torrentId, 'added' => now()->toDateTimeString(), 'text' => 'b', 'ori_text' => 'b'],
            ['user' => 3, 'torrent' => 999, 'added' => now()->toDateTimeString(), 'text' => 'c', 'ori_text' => 'c'],
        ]);

        $this->assertSame(2, $this->repository->getCommentCount($torrentId));
    }

    public function test_get_comments_returns_empty_array_when_none(): void
    {
        $torrentId = $this->createTorrent();

        $this->assertSame([], $this->repository->getComments($torrentId, 0, 10));
    }

    public function test_get_comments_returns_paginated_rows_ordered_by_id(): void
    {
        $torrentId = $this->createTorrent();

        Comment::query()->insert([
            ['user' => 1, 'torrent' => $torrentId, 'added' => now()->toDateTimeString(), 'text' => 'first', 'ori_text' => 'first'],
            ['user' => 2, 'torrent' => $torrentId, 'added' => now()->toDateTimeString(), 'text' => 'second', 'ori_text' => 'second'],
            ['user' => 3, 'torrent' => $torrentId, 'added' => now()->toDateTimeString(), 'text' => 'third', 'ori_text' => 'third'],
        ]);

        $result = $this->repository->getComments($torrentId, 0, 2);

        $this->assertCount(2, $result);
        $this->assertSame('first', $result[0]['text']);
        $this->assertSame('second', $result[1]['text']);
    }

    public function test_get_comments_respects_offset(): void
    {
        $torrentId = $this->createTorrent();

        Comment::query()->insert([
            ['user' => 1, 'torrent' => $torrentId, 'added' => now()->toDateTimeString(), 'text' => 'first', 'ori_text' => 'first'],
            ['user' => 2, 'torrent' => $torrentId, 'added' => now()->toDateTimeString(), 'text' => 'second', 'ori_text' => 'second'],
        ]);

        $result = $this->repository->getComments($torrentId, 1, 10);

        $this->assertCount(1, $result);
        $this->assertSame('second', $result[0]['text']);
    }

    public function test_increment_views_increases_views_by_one(): void
    {
        $torrentId = $this->createTorrent(['views' => 5]);

        $this->repository->incrementViews($torrentId);

        $torrent = Torrent::query()->find($torrentId);
        $this->assertNotNull($torrent);
        $this->assertSame(6, (int) $torrent->views);
    }

    public function test_get_tag_ids_returns_empty_array_when_none(): void
    {
        $torrentId = $this->createTorrent();

        $this->assertSame([], $this->repository->getTagIds($torrentId));
    }

    public function test_get_tag_ids_returns_tag_ids_for_torrent(): void
    {
        $torrentId = $this->createTorrent();

        TorrentTag::query()->insert([
            ['torrent_id' => $torrentId, 'tag_id' => 100, 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
            ['torrent_id' => $torrentId, 'tag_id' => 200, 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
            ['torrent_id' => 999, 'tag_id' => 300, 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
        ]);

        $result = $this->repository->getTagIds($torrentId);

        $this->assertSame([100, 200], $result);
    }

    public function test_get_latest_approval_deny_log_returns_null_when_none(): void
    {
        $torrentId = $this->createTorrent();

        $this->assertNull($this->repository->getLatestApprovalDenyLog($torrentId));
    }

    public function test_get_latest_approval_deny_log_returns_latest_deny(): void
    {
        $torrentId = $this->createTorrent();

        TorrentOperationLog::query()->insert([
            ['torrent_id' => $torrentId, 'uid' => 1, 'action_type' => TorrentOperationAction::APPROVAL_DENY->value, 'comment' => 'first deny', 'created_at' => now()->subHour()->toDateTimeString(), 'updated_at' => now()->subHour()->toDateTimeString()],
            ['torrent_id' => $torrentId, 'uid' => 2, 'action_type' => TorrentOperationAction::APPROVAL_DENY->value, 'comment' => 'second deny', 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
            ['torrent_id' => $torrentId, 'uid' => 3, 'action_type' => 'approval_pass', 'comment' => 'pass', 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString()],
        ]);

        $result = $this->repository->getLatestApprovalDenyLog($torrentId);

        $this->assertNotNull($result);
        $this->assertSame('second deny', $result->comment);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTorrent(array $overrides = []): int
    {
        return (int) DB::table('torrents')->insertGetId(array_merge([
            'name' => 'Test Torrent',
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => 1,
            'size' => 1024,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => 1,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'views' => 0,
            'added' => now()->toDateTimeString(),
        ], $overrides));
    }
}
