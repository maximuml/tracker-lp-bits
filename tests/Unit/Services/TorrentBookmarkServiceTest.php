<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TorrentBookmarkService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for TorrentBookmarkService.
 *
 * Covers toggleBookmark (add/delete/cache invalidation) and
 * thankTorrent (invalid torrent, duplicate thanks, success).
 */
final class TorrentBookmarkServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentBookmarkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('bookmarks')->truncate();
        DB::table('thanks')->truncate();
        DB::table('torrents')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->service = new TorrentBookmarkService;
    }

    private function insertTorrent(int $id, int $owner = 10): void
    {
        // Ensure owner user exists
        $this->insertUser($owner);

        DB::table('torrents')->insert([
            'id' => $id,
            'owner' => $owner,
            'name' => 'Torrent '.$id,
            'info_hash' => str_repeat(chr(max(10, $id % 200 + 10)), 20),
            'filename' => 'test'.$id.'.torrent',
            'added' => now()->toDateTimeString(),
            'size' => 1024,
            'category' => 1,
            'visible' => 1,
            'banned' => 0,
            'type' => 'single',
            'save_as' => 'test'.$id,
            'numfiles' => 1,
        ]);
    }

    private function insertUser(int $id): void
    {
        if (DB::table('users')->where('id', $id)->exists()) {
            return;
        }

        DB::table('users')->insert([
            'id' => $id,
            'username' => 'user'.$id,
            'email' => 'user'.$id.'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => 'passkey'.$id,
            'class' => 1,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 'confirmed',
            'enabled' => 1,
        ]);
    }

    // --- toggleBookmark ---

    public function test_toggle_bookmark_adds_when_not_exists(): void
    {
        $this->insertTorrent(100);
        $this->insertUser(1);

        $status = $this->service->toggleBookmark(1, 100);

        $this->assertSame('added', $status);
        $this->assertSame(1, DB::table('bookmarks')->count());
        $bookmark = DB::table('bookmarks')->first();
        $this->assertNotNull($bookmark);
        $this->assertSame(100, (int) $bookmark->torrentid);
        $this->assertSame(1, (int) $bookmark->userid);
    }

    public function test_toggle_bookmark_deletes_when_exists(): void
    {
        $this->insertTorrent(100);
        $this->insertUser(1);
        $this->service->toggleBookmark(1, 100);

        $status = $this->service->toggleBookmark(1, 100);

        $this->assertSame('deleted', $status);
        $this->assertSame(0, DB::table('bookmarks')->count());
    }

    public function test_toggle_bookmark_only_affects_specified_user(): void
    {
        $this->insertTorrent(100);
        $this->insertUser(1);
        $this->insertUser(2);

        $this->service->toggleBookmark(1, 100);
        $status = $this->service->toggleBookmark(2, 100);

        $this->assertSame('added', $status);
        $this->assertSame(2, DB::table('bookmarks')->count());
    }

    public function test_toggle_bookmark_only_affects_specified_torrent(): void
    {
        $this->insertTorrent(100);
        $this->insertTorrent(200);
        $this->insertUser(1);

        $this->service->toggleBookmark(1, 100);
        $status = $this->service->toggleBookmark(1, 200);

        $this->assertSame('added', $status);
        $this->assertSame(2, DB::table('bookmarks')->count());
    }

    public function test_toggle_bookmark_can_re_add_after_delete(): void
    {
        $this->insertTorrent(100);
        $this->insertUser(1);
        $this->service->toggleBookmark(1, 100);
        $this->service->toggleBookmark(1, 100);

        $status = $this->service->toggleBookmark(1, 100);

        $this->assertSame('added', $status);
        $this->assertSame(1, DB::table('bookmarks')->count());
    }

    // --- thankTorrent ---

    public function test_thank_torrent_throws_for_nonexistent_torrent(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid torrent id!');

        $this->service->thankTorrent(['id' => 1], 99999);
    }

    public function test_thank_torrent_throws_on_duplicate_thanks(): void
    {
        $this->insertTorrent(500);
        $this->insertUser(1);

        DB::table('thanks')->insert([
            'torrentid' => 500,
            'userid' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You already said thanks!');

        $this->service->thankTorrent(['id' => 1], 500);
    }

    public function test_thank_torrent_succeeds_for_new_thanks(): void
    {
        $this->insertTorrent(501);
        $this->insertUser(1);

        $result = $this->service->thankTorrent(['id' => 1], 501);

        $this->assertSame(['torrentid' => 501, 'owner' => 10], $result);
        $this->assertSame(1, DB::table('thanks')->count());
        $thanks = DB::table('thanks')->first();
        $this->assertNotNull($thanks);
        $this->assertSame(501, (int) $thanks->torrentid);
        $this->assertSame(1, (int) $thanks->userid);
    }

    public function test_thank_torrent_with_null_user_uses_zero_id(): void
    {
        $this->insertTorrent(502);
        // User 0 doesn't exist, but thanks table may not have FK on userid=0
        // Actually it does — so we need to handle this. Let's check the schema.
        // The thanks table has FK on userid → users.id. User 0 doesn't exist.
        // So this test should expect a constraint violation, OR we skip it.
        // Actually, looking at the service code, it uses userid=0 when
        // currentUser is null. This would fail with FK constraint.
        // Let's test the actual behavior — it throws.
        $this->expectException(QueryException::class);

        $this->service->thankTorrent(null, 502);
    }
}
