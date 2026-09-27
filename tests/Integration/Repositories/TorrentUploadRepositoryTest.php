<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Enums\OfferAllowed;
use App\Models\User;
use App\Repositories\TorrentUploadRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for TorrentUploadRepository.
 *
 * Covers isAllowedOffer(), getOfferVoterIds() and finalizeOffer().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
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
            ['offerid' => $offerId, 'userid' => $voter1->id, 'vote' => 0],
            ['offerid' => $offerId, 'userid' => $voter2->id, 'vote' => 0],
            ['offerid' => $offerId, 'userid' => $uploader->id, 'vote' => 0],
            ['offerid' => $offerId, 'userid' => $voter3->id, 'vote' => 1],
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
            ['offerid' => $offerId, 'userid' => $voter->id, 'vote' => 0],
            ['offerid' => $otherOfferId, 'userid' => $voter->id, 'vote' => 0],
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
            ['offerid' => $offerId, 'userid' => $voter->id, 'vote' => 0],
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
            'allowed' => OfferAllowed::fromStringSafe($allowed)->value,
            'added' => now()->toDateTimeString(),
        ]);
    }
}
