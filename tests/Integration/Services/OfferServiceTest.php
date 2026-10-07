<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Repositories\MessageRepository;
use App\Repositories\OfferCommentRepository;
use App\Repositories\OfferRepository;
use App\Repositories\OfferVoteRepository;
use App\Services\OfferModerationService;
use App\Services\OfferService;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Unit tests for OfferService.
 *
 * Covers handleActionPublic routing (empty action, GET redirect,
 * unknown action), handleCreate (permission denied), handleAllow
 * (permission denied), handleFinish (permission denied), handleDelete
 * (invalid params, nonexistent offer, wrong owner, owner delete with
 * confirmation), and handleEdit (invalid params, wrong owner, owner
 * edit success).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class OfferServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLegacySettings;

    private int $initialObLevel;

    private OfferService $service;

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
        DB::table('staffmessages')->delete();
        DB::table('comments')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->currentUser = new CurrentUser;
        $this->service = new OfferService(
            $this->currentUser,
            new OfferRepository,
            new OfferVoteRepository,
            new OfferCommentRepository,
            new OfferModerationService($this->currentUser, new OfferRepository, new OfferVoteRepository, new MessageRepository),
            new MessageRepository,
        );
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
            'allowed' => 1,
            'yeah' => 0,
            'against' => 0,
            'comments' => 0,
        ], $overrides));
    }

    private function unauthenticatedUser(): void
    {
        $this->currentUser->set([]);
    }

    /** @param array<string, mixed> $userData */
    private function authenticatedUser(array $userData = []): void
    {
        $defaults = ['id' => 1, 'username' => 'testuser', 'class' => 1];
        $this->currentUser->set(array_merge($defaults, $userData));
    }

    private function seedSettings(): void
    {
        $this->seedTestSettings(['BASEURL' => 'example.com']);
    }

    /**
     * Call the service while suppressing E_NOTICE/E_WARNING from the
     * legacy rendering system triggered by LegacyResponse::abort().
     */
    private function callService(Request $request): mixed
    {
        set_error_handler(function (int $severity): bool {
            return true;
        }, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);

        try {
            return $this->service->handleActionPublic($request);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Assert that calling the service with $request triggers an abort/guard.
     *
     * LegacyResponse::abort() throws HttpResponseException, but the legacy
     * rendering may also throw TypeError or ErrorException in the test
     * environment. Any Throwable from the guard path indicates the abort
     * was triggered.
     */
    private function assertServiceThrows(Request $request): void
    {
        $threw = false;
        try {
            $this->callService($request);
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected exception was not thrown');
    }

    /**
     * Same as assertServiceThrows but pins the abort's rendered body —
     * distinguishes a removed guard abort from the downstream aborts that
     * would otherwise mask it.
     */
    private function assertAbortContent(Request $request, string ...$needles): void
    {
        try {
            $this->callService($request);
            $this->fail('Expected abort');
        } catch (HttpResponseException $e) {
            $html = (string) $e->getResponse()->getContent();
            foreach ($needles as $needle) {
                $this->assertStringContainsString(e($needle), $html);
            }
        } catch (\Throwable $e) {
            $this->fail('Expected HttpResponseException, got '.$e::class);
        }
    }

    // --- handleActionPublic: routing ---

    public function test_handle_action_returns_null_for_empty_action(): void
    {
        $this->unauthenticatedUser();
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST');

        $this->assertNull($this->callService($request));
    }

    public function test_handle_action_returns_redirect_for_get_request(): void
    {
        $this->unauthenticatedUser();
        $this->seedSettings();

        $request = Request::create('/offers.php', 'GET', ['new_offer' => 1]);

        $result = $this->callService($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('/web/offers', $result->getTargetUrl());
    }

    public function test_handle_action_returns_null_for_unknown_action(): void
    {
        $this->unauthenticatedUser();
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', ['unknown_action' => 1]);

        $this->assertNull($this->callService($request));
    }

    // --- handleCreate: permission denied ---

    public function test_handle_create_throws_without_permission(): void
    {
        $this->unauthenticatedUser();
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'new_offer' => 1,
            'name' => 'Test Offer',
            'type' => 1,
            'body' => 'Description',
        ]);

        $this->assertServiceThrows($request);
    }

    // --- handleAllow: permission denied ---

    public function test_handle_allow_throws_without_permission(): void
    {
        $this->unauthenticatedUser();
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'allow_offer' => 1,
            'offerid' => 1,
        ]);

        $this->assertAbortContent($request, (string) __('offers.std_access_denied'), (string) __('offers.std_mans_job'));
    }

    // --- handleFinish: permission denied ---

    public function test_handle_finish_throws_without_permission(): void
    {
        $this->unauthenticatedUser();
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'finish_offer' => 1,
            'finish' => 1,
        ]);

        $this->assertAbortContent($request, (string) __('offers.std_access_denied'), (string) __('offers.std_have_no_permission'));
    }

    public function test_delete_and_edit_are_public_entrypoints(): void
    {
        foreach (['handleDelete', 'handleEdit'] as $method) {
            $this->assertTrue(
                (new \ReflectionMethod(OfferService::class, $method))->isPublic(),
                "{$method} must stay public — OfferController calls it directly",
            );
        }
    }

    // --- handleDelete: invalid del_offer value ---

    public function test_handle_delete_throws_for_invalid_del_offer_value(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId, 'username' => 'testuser']);
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'del_offer' => 0,
            'id' => 1,
        ]);

        $this->assertServiceThrows($request);
    }

    // --- handleDelete: invalid id ---

    public function test_handle_delete_throws_for_invalid_id(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId, 'username' => 'testuser']);
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'del_offer' => 1,
            'id' => 0,
        ]);

        $this->assertServiceThrows($request);
    }

    // --- handleDelete: nonexistent offer ---

    public function test_handle_delete_throws_for_nonexistent_offer(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId, 'username' => 'testuser']);
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'del_offer' => 1,
            'id' => 999,
        ]);

        $this->assertServiceThrows($request);
    }

    // --- handleDelete: wrong owner without manage permission ---

    public function test_handle_delete_throws_for_wrong_owner(): void
    {
        $userId = $this->createUser();
        $otherUserId = $this->createUser();
        $offerId = $this->insertOffer($otherUserId);
        $this->authenticatedUser(['id' => $userId, 'username' => 'testuser']);
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'del_offer' => 1,
            'id' => $offerId,
        ]);

        $this->assertServiceThrows($request);
    }

    // --- handleDelete: owner gets confirmation form with sure=0 ---

    public function test_handle_delete_owner_gets_confirmation_form(): void
    {
        $userId = $this->createUser();
        $offerId = $this->insertOffer($userId);
        $this->authenticatedUser(['id' => $userId, 'username' => 'testuser']);
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'del_offer' => 1,
            'id' => $offerId,
            'sure' => 0,
        ]);

        // sure=0 triggers abort with confirmation form (die=false → echo + throw)
        $this->assertServiceThrows($request);
    }

    // --- handleDelete: owner can delete with sure=1 ---

    public function test_handle_delete_owner_succeeds_with_confirmation(): void
    {
        $userId = $this->createUser();
        $offerId = $this->insertOffer($userId);
        $this->authenticatedUser(['id' => $userId, 'username' => 'testuser']);
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'del_offer' => 1,
            'id' => $offerId,
            'sure' => 1,
            'reason' => 'Not needed',
        ]);

        $result = $this->callService($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('/web/offers', $result->getTargetUrl());
        $this->assertSame(0, DB::table('offers')->where('id', $offerId)->count());
    }

    // --- handleEdit: invalid take_off_edit value ---

    public function test_handle_edit_throws_for_invalid_take_off_edit_value(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId, 'username' => 'testuser']);
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'take_off_edit' => 0,
            'id' => 1,
        ]);

        $this->assertServiceThrows($request);
    }

    // --- handleEdit: invalid id ---

    public function test_handle_edit_throws_for_invalid_id(): void
    {
        $userId = $this->createUser();
        $this->authenticatedUser(['id' => $userId, 'username' => 'testuser']);
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'take_off_edit' => 1,
            'id' => 0,
        ]);

        $this->assertServiceThrows($request);
    }

    // --- handleEdit: wrong owner without manage permission ---

    public function test_handle_edit_throws_for_wrong_owner(): void
    {
        $userId = $this->createUser();
        $otherUserId = $this->createUser();
        $offerId = $this->insertOffer($otherUserId);
        $this->authenticatedUser(['id' => $userId, 'username' => 'testuser']);
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'take_off_edit' => 1,
            'id' => $offerId,
            'name' => 'Updated',
            'body' => 'Updated desc',
            'category' => 1,
        ]);

        $this->assertServiceThrows($request);
    }

    // --- handleEdit: owner can edit ---

    public function test_handle_edit_owner_succeeds(): void
    {
        $userId = $this->createUser();
        $offerId = $this->insertOffer($userId);
        $this->authenticatedUser(['id' => $userId, 'username' => 'testuser']);
        $this->seedSettings();

        $request = Request::create('/offers.php', 'POST', [
            'take_off_edit' => 1,
            'id' => $offerId,
            'name' => 'Updated Name',
            'body' => 'Updated description',
            'category' => 1,
        ]);

        $result = $this->callService($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertStringContainsString('/web/offers', $result->getTargetUrl());

        $offer = DB::table('offers')->where('id', $offerId)->first();
        $this->assertNotNull($offer);
        $this->assertSame('Updated Name', $offer->name);
    }
}
