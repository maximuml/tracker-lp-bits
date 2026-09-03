<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\UserClass;
use App\Exceptions\NexusException;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TorrentEditRepository;
use App\Repositories\UploadRepository;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for TorrentEditRepository.
 *
 * Covers update() error guards (unauthenticated, missing id, torrent not
 * found, not-owner, missing form data, invalid category) and a successful
 * owner edit.
 *
 * UploadRepository is mocked to isolate the edit logic from the upload
 * pipeline (sub-category/tag validation, custom fields, cover extraction).
 */
final class TorrentEditRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentEditRepository $repository;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        Permissions::resetState();

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('torrent_extras')->delete();
        DB::table('torrent_tags')->delete();
        DB::table('torrents')->delete();
        DB::table('categories')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->categoryId = (int) DB::table('categories')->insertGetId([
            'name' => 'Movies',
            'mode' => 1,
            'class_name' => 'movies',
        ]);

        $uploadMock = Mockery::mock(UploadRepository::class);
        $uploadMock->shouldReceive('getSubCategoriesAndTags')
            ->andReturn(['subCategories' => [], 'tags' => []]);
        $uploadMock->shouldReceive('saveCustomFields');
        $uploadMock->shouldReceive('getHitAndRun')->andReturn(0);
        $uploadMock->shouldReceive('getPrice')->andReturn(0);
        $uploadMock->shouldReceive('getCover')->andReturn('');

        $this->repository = new TorrentEditRepository($uploadMock); // @phpstan-ignore argument.type
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_update_throws_when_not_authenticated(): void
    {
        $request = Request::create('/takeedit.php', 'POST', ['id' => 1]);

        $this->expectException(NexusException::class);

        $this->repository->update($request);
    }

    public function test_update_throws_when_id_is_missing(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::USER->value)->create();
        Auth::login($user);

        $request = Request::create('/takeedit.php', 'POST', []);

        $this->expectException(NexusException::class);

        $this->repository->update($request);
    }

    public function test_update_throws_when_torrent_not_found(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::USER->value)->create();
        Auth::login($user);

        $request = Request::create('/takeedit.php', 'POST', ['id' => 99999]);

        $this->expectException(NexusException::class);

        $this->repository->update($request);
    }

    public function test_update_throws_when_not_owner_and_cannot_manage(): void
    {
        /** @var User $owner */
        $owner = User::factory()->class(UserClass::USER->value)->create();
        /** @var User $other */
        $other = User::factory()->class(UserClass::USER->value)->create();
        Auth::login($other);

        $torrentId = $this->createTorrent($owner->id);

        $request = Request::create('/takeedit.php', 'POST', [
            'id' => $torrentId,
            'name' => 'New Name',
            'descr' => 'New description',
            'type' => $this->categoryId,
        ]);

        $this->expectException(NexusException::class);

        $this->repository->update($request);
    }

    public function test_update_throws_when_name_is_empty(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::USER->value)->create();
        Auth::login($user);

        $torrentId = $this->createTorrent($user->id);

        $request = Request::create('/takeedit.php', 'POST', [
            'id' => $torrentId,
            'name' => '',
            'descr' => 'description',
            'type' => $this->categoryId,
        ]);

        $this->expectException(NexusException::class);

        $this->repository->update($request);
    }

    public function test_update_throws_when_descr_is_empty(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::USER->value)->create();
        Auth::login($user);

        $torrentId = $this->createTorrent($user->id);

        $request = Request::create('/takeedit.php', 'POST', [
            'id' => $torrentId,
            'name' => 'New Name',
            'descr' => '',
            'type' => $this->categoryId,
        ]);

        $this->expectException(NexusException::class);

        $this->repository->update($request);
    }

    public function test_update_throws_when_category_not_found(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::USER->value)->create();
        Auth::login($user);

        $torrentId = $this->createTorrent($user->id);

        $request = Request::create('/takeedit.php', 'POST', [
            'id' => $torrentId,
            'name' => 'New Name',
            'descr' => 'description',
            'type' => 99999,
        ]);

        $this->expectException(NexusException::class);

        $this->repository->update($request);
    }

    public function test_update_succeeds_as_owner(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::USER->value)->create();
        Auth::login($user);

        $torrentId = $this->createTorrent($user->id);

        $request = Request::create('/takeedit.php', 'POST', [
            'id' => $torrentId,
            'name' => 'Updated Torrent Name',
            'descr' => 'Updated description text',
            'type' => $this->categoryId,
            'anonymous' => 1,
        ]);

        $torrent = $this->repository->update($request);

        $this->assertSame($torrentId, $torrent->id);
        $this->assertSame('Updated Torrent Name', $torrent->name);
        $this->assertSame(1, (int) $torrent->anonymous);

        $row = DB::table('torrents')->where('id', $torrentId)->first();
        $this->assertNotNull($row);
        $this->assertSame('Updated Torrent Name', $row->name);
        $this->assertSame(1, (int) $row->anonymous);

        $extra = DB::table('torrent_extras')->where('torrent_id', $torrentId)->first();
        $this->assertNotNull($extra);
        $this->assertSame('Updated description text', $extra->descr);
    }

    public function test_update_clears_url_field(): void
    {
        /** @var User $user */
        $user = User::factory()->class(UserClass::USER->value)->create();
        Auth::login($user);

        $torrentId = $this->createTorrent($user->id);
        DB::table('torrents')->where('id', $torrentId)->update(['url' => 123]);

        $request = Request::create('/takeedit.php', 'POST', [
            'id' => $torrentId,
            'name' => 'Updated Name',
            'descr' => 'description',
            'type' => $this->categoryId,
        ]);

        $this->repository->update($request);

        $row = DB::table('torrents')->where('id', $torrentId)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->url);
    }

    private function createTorrent(int $ownerId): int
    {
        return (int) DB::table('torrents')->insertGetId([
            'name' => 'Original Torrent',
            'filename' => 'test.torrent',
            'save_as' => 'test',
            'category' => $this->categoryId,
            'size' => 1024,
            'type' => 'single',
            'numfiles' => 1,
            'owner' => $ownerId,
            'info_hash' => random_bytes(20),
            'visible' => 1,
            'banned' => 0,
            'added' => now()->toDateTimeString(),
        ]);
    }
}
