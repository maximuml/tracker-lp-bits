<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\TorrentUploadRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for TorrentUploadRepository.
 *
 * Covers getCategoryMode(), allowedOfferCount(), isAllowedOffer(),
 * rollbackTorrent(), syncFiles(), getOfferVoterIds() and finalizeOffer().
 */
final class TorrentUploadRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentUploadRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('files')->delete();
        DB::table('offervotes')->delete();
        DB::table('offers')->delete();
        DB::table('comments')->delete();
        DB::table('torrents')->delete();
        DB::table('categories')->delete();
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new TorrentUploadRepository;
    }

    public function test_get_category_mode_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getCategoryMode(99999));
    }

    public function test_get_category_mode_returns_mode_when_found(): void
    {
        $id = (int) DB::table('categories')->insertGetId([
            'name' => 'Movies',
            'mode' => 2,
            'class_name' => 'movies',
            'image' => 'movies.gif',
        ]);

        $this->assertSame('2', $this->repository->getCategoryMode($id));
    }

    public function test_allowed_offer_count_returns_zero_when_no_records(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertSame(0, $this->repository->allowedOfferCount($user->id));
    }

    public function test_allowed_offer_count_counts_only_allowed_for_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();
        $this->createOffer($user->id, 'allowed');
        $this->createOffer($user->id, 'allowed');
        $this->createOffer($user->id, 'pending');
        $this->createOffer($other->id, 'allowed');

        $this->assertSame(2, $this->repository->allowedOfferCount($user->id));
    }

    public function test_is_allowed_offer_returns_false_when_not_found(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->assertFalse($this->repository->isAllowedOffer(99999, $user->id));
    }

    public function test_is_allowed_offer_returns_true_when_match(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $offerId = $this->createOffer($user->id, 'allowed');

        $this->assertTrue($this->repository->isAllowedOffer($offerId, $user->id));
    }

    public function test_is_allowed_offer_returns_false_when_wrong_user(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $other */
        $other = User::factory()->create();
        $offerId = $this->createOffer($user->id, 'allowed');

        $this->assertFalse($this->repository->isAllowedOffer($offerId, $other->id));
    }

    public function test_is_allowed_offer_returns_false_when_not_allowed(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $offerId = $this->createOffer($user->id, 'pending');

        $this->assertFalse($this->repository->isAllowedOffer($offerId, $user->id));
    }

    public function test_rollback_torrent_deletes_torrent(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $torrentId = $this->createTorrent($user->id);

        $this->assertSame(1, DB::table('torrents')->where('id', $torrentId)->count());

        $this->repository->rollbackTorrent($torrentId);

        $this->assertSame(0, DB::table('torrents')->where('id', $torrentId)->count());
    }

    public function test_rollback_torrent_is_noop_when_not_found(): void
    {
        $this->repository->rollbackTorrent(99999);

        $this->expectNotToPerformAssertions();
    }

    public function test_sync_files_replaces_existing_files(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $torrentId = $this->createTorrent($user->id);

        DB::table('files')->insert([
            ['torrent' => $torrentId, 'filename' => 'old.txt', 'size' => 100],
        ]);

        $this->repository->syncFiles($torrentId, [
            ['path' => 'new1.txt', 'size' => 200],
            ['path' => 'new2.txt', 'size' => 300],
        ]);

        $files = DB::table('files')->where('torrent', $torrentId)->orderBy('filename')->get();

        $this->assertSame(2, $files->count());
        $first = $files->get(0);
        $second = $files->get(1);
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame('new1.txt', $first->filename);
        $this->assertSame(200, (int) $first->size);
        $this->assertSame('new2.txt', $second->filename);
        $this->assertSame(300, (int) $second->size);
    }

    public function test_sync_files_with_empty_list_clears_files(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $torrentId = $this->createTorrent($user->id);

        DB::table('files')->insert([
            ['torrent' => $torrentId, 'filename' => 'old.txt', 'size' => 100],
        ]);

        $this->repository->syncFiles($torrentId, []);

        $this->assertSame(0, DB::table('files')->where('torrent', $torrentId)->count());
    }

    public function test_get_offer_voter_ids_returns_empty_when_no_votes(): void
    {
        /** @var User $uploader */
        $uploader = User::factory()->create();
        $offerId = $this->createOffer($uploader->id, 'allowed');

        $this->assertSame([], $this->repository->getOfferVoterIds($offerId, $uploader->id));
    }

    public function test_get_offer_voter_ids_excludes_uploader_and_non_yeah_votes(): void
    {
        /** @var User $uploader */
        $uploader = User::factory()->create();
        /** @var User $voter1 */
        $voter1 = User::factory()->create();
        /** @var User $voter2 */
        $voter2 = User::factory()->create();
        /** @var User $voter3 */
        $voter3 = User::factory()->create();
        $offerId = $this->createOffer($uploader->id, 'allowed');

        DB::table('offervotes')->insert([
            ['offerid' => $offerId, 'userid' => $voter1->id, 'vote' => 'yeah'],
            ['offerid' => $offerId, 'userid' => $voter2->id, 'vote' => 'yeah'],
            ['offerid' => $offerId, 'userid' => $uploader->id, 'vote' => 'yeah'],
            ['offerid' => $offerId, 'userid' => $voter3->id, 'vote' => 'against'],
        ]);

        $result = $this->repository->getOfferVoterIds($offerId, $uploader->id);
        sort($result);

        $this->assertSame([$voter1->id, $voter2->id], $result);
    }

    public function test_get_offer_voter_ids_excludes_votes_for_other_offers(): void
    {
        /** @var User $uploader */
        $uploader = User::factory()->create();
        /** @var User $voter */
        $voter = User::factory()->create();
        $offerId = $this->createOffer($uploader->id, 'allowed');
        $otherOfferId = $this->createOffer($uploader->id, 'allowed');

        DB::table('offervotes')->insert([
            ['offerid' => $offerId, 'userid' => $voter->id, 'vote' => 'yeah'],
            ['offerid' => $otherOfferId, 'userid' => $voter->id, 'vote' => 'yeah'],
        ]);

        $result = $this->repository->getOfferVoterIds($offerId, $uploader->id);

        $this->assertSame([$voter->id], $result);
    }

    public function test_finalize_offer_deletes_related_records_and_increments_user(): void
    {
        /** @var User $uploader */
        $uploader = User::factory()->create();
        /** @var User $voter */
        $voter = User::factory()->create();
        /** @var User $commenter */
        $commenter = User::factory()->create();
        $offerId = $this->createOffer($uploader->id, 'allowed');
        DB::table('offervotes')->insert([
            ['offerid' => $offerId, 'userid' => $voter->id, 'vote' => 'yeah'],
        ]);
        DB::table('comments')->insert([
            ['user' => $commenter->id, 'offer' => $offerId, 'added' => now()->toDateTimeString(), 'text' => 'c', 'ori_text' => 'c'],
        ]);

        $before = (int) DB::table('users')->where('id', $uploader->id)->value('offer_allowed_count');

        $this->repository->finalizeOffer($offerId, $uploader->id);

        $this->assertSame(0, DB::table('offers')->where('id', $offerId)->count());
        $this->assertSame(0, DB::table('offervotes')->where('offerid', $offerId)->count());
        $this->assertSame(0, DB::table('comments')->where('offer', $offerId)->count());
        $after = (int) DB::table('users')->where('id', $uploader->id)->value('offer_allowed_count');
        $this->assertSame($before + 1, $after);
    }

    private function createOffer(int $userId, string $allowed): int
    {
        return (int) DB::table('offers')->insertGetId([
            'userid' => $userId,
            'name' => 'offer',
            'allowed' => $allowed,
            'added' => now()->toDateTimeString(),
        ]);
    }

    private function createTorrent(int $ownerId): int
    {
        return (int) DB::table('torrents')->insertGetId([
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
    }
}
