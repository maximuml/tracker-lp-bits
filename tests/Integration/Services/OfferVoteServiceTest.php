<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\OfferAllowed;
use App\Enums\OfferVote;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Repositories\OfferRepository;
use App\Repositories\OfferVoteRepository;
use App\Services\OfferVoteService;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;
use Throwable;

/**
 * Integration tests for OfferVoteService.
 *
 * Covers handleVote: empty vote (null), invalid vote (abort), missing
 * offer (abort), owner self-vote (abort), duplicate vote (abort),
 * successful yeah/against votes (record + counter increment), and the
 * allow/deny threshold paths (offer allowed/denied + owner PM).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class OfferVoteServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLegacySettings;

    private int $initialObLevel;

    private OfferVoteService $service;

    private CurrentUser $currentUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialObLevel = ob_get_level();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('offers')->delete();
        DB::table('offervotes')->delete();
        DB::table('users')->delete();
        DB::table('messages')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->currentUser = new CurrentUser;
        $this->service = new OfferVoteService(
            $this->currentUser,
            new OfferRepository,
            new OfferVoteRepository,
            new MessageRepository,
        );
        $this->seedTestSettings(['BASEURL' => 'example.com']);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }
        parent::tearDown();
    }

    /** @param array<string, mixed> $overrides */
    private function createUser(array $overrides = []): int
    {
        return (int) DB::table('users')->insertGetId(array_merge([
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'class' => 1,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 1,
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 100.0,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function insertOffer(int $userId, array $overrides = []): int
    {
        return (int) DB::table('offers')->insertGetId(array_merge([
            'userid' => $userId,
            'name' => 'Test Offer '.uniqid(),
            'descr' => 'Test description',
            'added' => now()->toDateTimeString(),
            'category' => 1,
            'allowed' => OfferAllowed::PENDING->value,
            'yeah' => 0,
            'against' => 0,
            'comments' => 0,
        ], $overrides));
    }

    /** @param array<string, mixed> $userData */
    private function authenticatedUser(array $userData = []): void
    {
        $defaults = ['id' => 1, 'username' => 'testuser', 'class' => 1, 'seedbonus' => 0.0];
        $this->currentUser->set(array_merge($defaults, $userData));
    }

    /**
     * Call the service while suppressing E_NOTICE/E_WARNING from the
     * legacy rendering system triggered by PageResponses::abort().
     */
    /** @param array<string, mixed> $params */
    private function callVote(array $params): mixed
    {
        $request = Request::create('/web/offers/vote', 'POST', $params);

        set_error_handler(function (int $severity): bool {
            return true;
        }, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);

        try {
            return $this->service->handleVote($request);
        } finally {
            restore_error_handler();
        }
    }

    public function test_empty_vote_returns_null(): void
    {
        $this->authenticatedUser();
        $this->assertNull($this->callVote([]));
        $this->assertNull($this->callVote(['vote' => '']));
    }

    public function test_invalid_vote_aborts(): void
    {
        $this->authenticatedUser();
        $this->expectException(Throwable::class);
        $this->callVote(['id' => 1, 'vote' => 'bogus']);
    }

    public function test_missing_offer_aborts(): void
    {
        $this->authenticatedUser(['id' => 1]);
        try {
            $this->callVote(['id' => 999, 'vote' => 'yeah']);
            $this->fail('Expected abort');
        } catch (HttpResponseException $e) {
            $this->assertStringContainsString(e((string) __('offers.text_nothing_found')), (string) $e->getResponse()->getContent());
        }
    }

    public function test_missing_id_aborts(): void
    {
        $ownerId = $this->createUser();
        $this->insertOffer($ownerId);
        $voterId = $this->createUser();
        $this->authenticatedUser(['id' => $voterId]);

        $this->expectException(Throwable::class);
        $this->callVote(['vote' => 'yeah']);
    }

    public function test_owner_cannot_vote_own_offer(): void
    {
        $ownerId = $this->createUser();
        $offerId = $this->insertOffer($ownerId);
        $this->authenticatedUser(['id' => $ownerId]);

        $this->expectException(Throwable::class);
        $this->callVote(['id' => $offerId, 'vote' => 'yeah']);
    }

    public function test_duplicate_vote_aborts(): void
    {
        $ownerId = $this->createUser();
        $voterId = $this->createUser();
        $offerId = $this->insertOffer($ownerId);
        DB::table('offervotes')->insert([
            'offerid' => $offerId,
            'userid' => $voterId,
            'vote' => OfferVote::YEAH->value,
        ]);
        $this->authenticatedUser(['id' => $voterId]);

        $this->expectException(Throwable::class);
        $this->callVote(['id' => $offerId, 'vote' => 'against']);
    }

    public function test_yeah_vote_records_and_increments(): void
    {
        $ownerId = $this->createUser();
        $voterId = $this->createUser();
        $offerId = $this->insertOffer($ownerId);
        $this->authenticatedUser(['id' => $voterId]);

        $result = $this->callVote(['id' => $offerId, 'vote' => 'yeah']);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(1, (int) DB::table('offers')->where('id', $offerId)->value('yeah'));
        $this->assertSame(0, (int) DB::table('offers')->where('id', $offerId)->value('against'));
        $this->assertDatabaseHas('offervotes', [
            'offerid' => $offerId,
            'userid' => $voterId,
            'vote' => OfferVote::YEAH->value,
        ]);
    }

    public function test_against_vote_records_and_increments(): void
    {
        $ownerId = $this->createUser();
        $voterId = $this->createUser();
        $offerId = $this->insertOffer($ownerId);
        $this->authenticatedUser(['id' => $voterId]);
        auth()->login(User::findOrFail($voterId));

        $result = $this->callVote(['id' => $offerId, 'vote' => 'against']);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(1, (int) DB::table('offers')->where('id', $offerId)->value('against'));
        $this->assertDatabaseHas('offervotes', [
            'offerid' => $offerId,
            'userid' => $voterId,
            'vote' => OfferVote::AGAINST->value,
        ]);
    }

    public function test_reaching_yeah_threshold_allows_offer_and_pms_owner(): void
    {
        $ownerId = $this->createUser();
        $voterId = $this->createUser();
        $offerId = $this->insertOffer($ownerId, ['yeah' => 14]);
        $this->authenticatedUser(['id' => $voterId]);

        $this->callVote(['id' => $offerId, 'vote' => 'yeah']);

        $offer = DB::table('offers')->where('id', $offerId)->first();
        $this->assertNotNull($offer);
        $this->assertSame(15, (int) $offer->yeah);
        $this->assertSame(OfferAllowed::ALLOWED->value, (int) $offer->allowed);
        $this->assertDatabaseHas('messages', ['receiver' => $ownerId]);
    }

    public function test_reaching_against_threshold_denies_offer(): void
    {
        $ownerId = $this->createUser();
        $voterId = $this->createUser();
        $offerId = $this->insertOffer($ownerId, ['against' => 14]);
        $this->authenticatedUser(['id' => $voterId]);
        auth()->login(User::findOrFail($voterId));

        $this->callVote(['id' => $offerId, 'vote' => 'against']);

        $offer = DB::table('offers')->where('id', $offerId)->first();
        $this->assertNotNull($offer);
        $this->assertSame(15, (int) $offer->against);
        $this->assertSame(OfferAllowed::DENIED->value, (int) $offer->allowed);
        $this->assertDatabaseHas('messages', ['receiver' => $ownerId]);
    }
}
