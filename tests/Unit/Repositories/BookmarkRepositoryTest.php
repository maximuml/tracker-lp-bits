<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Exceptions\NexusException;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\BookmarkRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for BookmarkRepository.
 *
 * Covers add() and remove() public methods: torrent-not-found rejection,
 * already-bookmarked rejection, successful add, not-bookmarked rejection
 * on remove, and successful remove.
 */
final class BookmarkRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private BookmarkRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('bookmarks')->truncate();
        DB::table('torrents')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new BookmarkRepository;
    }

    private function createTorrent(int $ownerId): Torrent
    {
        $id = (int) DB::table('torrents')->insertGetId([
            'name' => 'Test Torrent',
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => 1,
            'size' => 1024,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => $ownerId,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'added' => now()->toDateTimeString(),
        ]);

        return Torrent::query()->findOrFail($id);
    }

    public function test_add_throws_when_torrent_does_not_exist(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->expectException(NexusException::class);

        $this->repository->add($user, 99999);
    }

    public function test_add_throws_when_already_bookmarked(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $torrent = $this->createTorrent($user->id);

        $this->repository->add($user, $torrent->id);

        $this->expectException(NexusException::class);

        $this->repository->add($user, $torrent->id);
    }

    public function test_add_succeeds_and_creates_bookmark(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $torrent = $this->createTorrent($user->id);

        $result = $this->repository->add($user, $torrent->id);

        $this->assertSame($torrent->id, (int) $result->torrentid);
        $this->assertSame($user->id, (int) $result->userid);
        $this->assertSame(1, DB::table('bookmarks')->where('userid', $user->id)->count());
    }

    public function test_add_casts_string_torrent_id_to_int(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $torrent = $this->createTorrent($user->id);

        $result = $this->repository->add($user, (string) $torrent->id);

        $this->assertSame($torrent->id, (int) $result->torrentid);
    }

    public function test_remove_throws_when_not_bookmarked(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $torrent = $this->createTorrent($user->id);

        $this->expectException(NexusException::class);

        $this->repository->remove($user, $torrent->id);
    }

    public function test_remove_succeeds_and_deletes_bookmark(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $torrent = $this->createTorrent($user->id);

        $this->repository->add($user, $torrent->id);
        $this->assertSame(1, DB::table('bookmarks')->where('userid', $user->id)->count());

        $result = $this->repository->remove($user, $torrent->id);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('bookmarks')->where('userid', $user->id)->count());
    }
}
