<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Bookmark;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TorrentDownloadRepository;
use App\Repositories\TorrentModerationRepository;
use App\Repositories\TorrentPurchaseRepository;
use App\Repositories\TorrentRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for TorrentRepository.
 *
 * Covers getShareRatio(), getPaidIcon(), getBookmarkTorrentIds(),
 * findForUserValue(), getLastComment(), getSnatchInfo().
 */
final class TorrentRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TorrentRepository(
            app(TorrentDownloadRepository::class),
            app(TorrentPurchaseRepository::class),
            app(TorrentModerationRepository::class),
        );
    }

    public function test_get_share_ratio_returns_ratio_when_downloaded_positive(): void
    {
        $peer = new \stdClass;
        $peer->uploaded = 2000;
        $peer->downloaded = 1000;

        $ratio = $this->repository->getShareRatio($peer);

        $this->assertSame(2.0, $ratio);
    }

    public function test_get_share_ratio_returns_infinity_when_no_download(): void
    {
        $peer = new \stdClass;
        $peer->uploaded = 1000;
        $peer->downloaded = 0;

        $ratio = $this->repository->getShareRatio($peer);

        $this->assertSame('Infinity', $ratio);
    }

    public function test_get_share_ratio_returns_dashes_when_no_upload_and_no_download(): void
    {
        $peer = new \stdClass;
        $peer->uploaded = 0;
        $peer->downloaded = 0;

        $ratio = $this->repository->getShareRatio($peer);

        $this->assertSame('---', $ratio);
    }

    public function test_get_share_ratio_truncates_to_three_decimal_places(): void
    {
        $peer = new \stdClass;
        $peer->uploaded = 1000;
        $peer->downloaded = 3000;

        $ratio = $this->repository->getShareRatio($peer);

        $this->assertSame(0.333, $ratio);
    }

    public function test_get_paid_icon_returns_empty_string_when_price_zero(): void
    {
        $result = $this->repository->getPaidIcon(['price' => 0]);

        $this->assertSame('', $result);
    }

    public function test_get_paid_icon_returns_empty_string_when_price_missing(): void
    {
        $result = $this->repository->getPaidIcon([]);

        $this->assertSame('', $result);
    }

    public function test_get_paid_icon_returns_html_when_price_positive(): void
    {
        $result = $this->repository->getPaidIcon(['price' => 100]);

        $this->assertStringContainsString('<svg', $result);
        $this->assertStringContainsString('width="16"', $result);
        $this->assertStringContainsString('height="16"', $result);
    }

    public function test_get_paid_icon_uses_custom_size(): void
    {
        $result = $this->repository->getPaidIcon(['price' => 100], 32);

        $this->assertStringContainsString('width="32"', $result);
        $this->assertStringContainsString('height="32"', $result);
    }

    public function test_get_bookmark_torrent_ids_returns_zero_array_when_no_bookmarks(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->getBookmarkTorrentIds($user->id);

        $this->assertSame([0], $result);
    }

    public function test_get_bookmark_torrent_ids_returns_ids_when_bookmarks_exist(): void
    {
        $user = User::factory()->create();
        $torrent1 = Torrent::factory()->create();
        $torrent2 = Torrent::factory()->create();
        Bookmark::query()->create(['userid' => $user->id, 'torrentid' => $torrent1->id]);
        Bookmark::query()->create(['userid' => $user->id, 'torrentid' => $torrent2->id]);

        $result = $this->repository->getBookmarkTorrentIds($user->id);

        $this->assertCount(2, $result);
        $this->assertContains($torrent1->id, $result);
        $this->assertContains($torrent2->id, $result);
    }

    public function test_find_for_user_value_returns_torrent_array_when_found(): void
    {
        $torrent = Torrent::factory()->create();

        $result = $this->repository->findForUserValue($torrent->id);

        $this->assertNotNull($result);
        $this->assertSame($torrent->id, $result['id']);
    }

    public function test_find_for_user_value_returns_null_when_not_found(): void
    {
        $result = $this->repository->findForUserValue(999999);

        $this->assertNull($result);
    }

    public function test_get_last_comment_returns_null_when_no_comments(): void
    {
        $torrent = Torrent::factory()->create();

        $result = $this->repository->getLastComment($torrent->id);

        $this->assertNull($result);
    }

    public function test_get_last_comment_returns_latest_comment(): void
    {
        $torrent = Torrent::factory()->create();
        $user = User::factory()->create();
        DB::table('comments')->insert([
            'torrent' => $torrent->id,
            'user' => $user->id,
            'added' => '2025-01-01 10:00:00',
            'text' => 'First comment',
            'ori_text' => 'First comment',
            'anonymous' => 0,
        ]);
        DB::table('comments')->insert([
            'torrent' => $torrent->id,
            'user' => $user->id,
            'added' => '2025-01-02 10:00:00',
            'text' => 'Second comment',
            'ori_text' => 'Second comment',
            'anonymous' => 0,
        ]);

        $result = $this->repository->getLastComment($torrent->id);

        $this->assertNotNull($result);
        $this->assertSame('Second comment', $result['text']);
    }

    public function test_get_snatch_info_returns_false_when_no_snatch(): void
    {
        $torrent = Torrent::factory()->create();
        $user = User::factory()->create();

        $result = $this->repository->getSnatchInfo($torrent->id, $user->id);

        $this->assertFalse($result);
    }

    public function test_get_snatch_info_returns_array_when_snatch_exists(): void
    {
        $torrent = Torrent::factory()->create();
        $user = User::factory()->create();
        DB::table('snatched')->insert([
            'torrentid' => $torrent->id,
            'userid' => $user->id,
            'ip' => '127.0.0.1',
            'port' => 0,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'startdat' => now()->toDateTimeString(),
            'last_action' => now()->toDateTimeString(),
        ]);

        $result = $this->repository->getSnatchInfo($torrent->id, $user->id);

        $this->assertIsArray($result);
        $this->assertSame($torrent->id, $result['torrentid']);
        $this->assertSame($user->id, $result['userid']);
    }
}
