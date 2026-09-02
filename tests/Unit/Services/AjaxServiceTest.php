<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\AttendanceRepository;
use App\Repositories\BonusRepository;
use App\Repositories\ExamRepository;
use App\Repositories\MedalRepository;
use App\Repositories\TorrentRepository;
use App\Repositories\UserPasskeyRepository;
use App\Repositories\UserRepository;
use App\Services\AjaxService;
use App\Services\ShoutboxService;
use App\Support\CurrentUser;
use App\Support\Shoutbox;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for AjaxService.
 *
 * Covers the ALLOWED_ACTIONS whitelist, getOffer (found / not found),
 * approval validation, shoutbox validation (post / edit / delete / react),
 * clearShoutBox permission, addToken / removeToken validation, and
 * repository-delegated actions (buyMedal, claimTask, getPasskeyList,
 * toggleUserMedalStatus, attendanceRetroactive).
 */
final class AjaxServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AjaxService $service;

    /** @var MedalRepository&MockInterface */
    private MedalRepository $medalRepo;

    /** @var AttendanceRepository&MockInterface */
    private AttendanceRepository $attendanceRepo;

    /** @var UserRepository&MockInterface */
    private UserRepository $userRepo;

    /** @var TorrentRepository&MockInterface */
    private TorrentRepository $torrentRepo;

    /** @var BonusRepository&MockInterface */
    private BonusRepository $bonusRepo;

    /** @var ExamRepository&MockInterface */
    private ExamRepository $examRepo;

    /** @var UserPasskeyRepository&MockInterface */
    private UserPasskeyRepository $passkeyRepo;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('shoutbox')->truncate();
        DB::table('shoutbox_reactions')->truncate();
        DB::table('offers')->truncate();
        DB::table('users')->truncate();
        DB::table('personal_access_tokens')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        /** @var MedalRepository&MockInterface $medalRepo */
        $medalRepo = Mockery::mock(MedalRepository::class);
        $this->medalRepo = $medalRepo;

        /** @var AttendanceRepository&MockInterface $attendanceRepo */
        $attendanceRepo = Mockery::mock(AttendanceRepository::class);
        $this->attendanceRepo = $attendanceRepo;

        /** @var UserRepository&MockInterface $userRepo */
        $userRepo = Mockery::mock(UserRepository::class);
        $this->userRepo = $userRepo;

        /** @var TorrentRepository&MockInterface $torrentRepo */
        $torrentRepo = Mockery::mock(TorrentRepository::class);
        $this->torrentRepo = $torrentRepo;

        /** @var BonusRepository&MockInterface $bonusRepo */
        $bonusRepo = Mockery::mock(BonusRepository::class);
        $this->bonusRepo = $bonusRepo;

        /** @var ExamRepository&MockInterface $examRepo */
        $examRepo = Mockery::mock(ExamRepository::class);
        $this->examRepo = $examRepo;

        /** @var UserPasskeyRepository&MockInterface $passkeyRepo */
        $passkeyRepo = Mockery::mock(UserPasskeyRepository::class);
        $this->passkeyRepo = $passkeyRepo;

        $this->service = new AjaxService(
            $this->medalRepo,
            $this->attendanceRepo,
            $this->userRepo,
            $this->torrentRepo,
            $this->bonusRepo,
            $this->examRepo,
            $this->passkeyRepo,
            new ShoutboxService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
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
            'status' => 'confirmed',
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 100.0,
        ], $overrides));
    }

    private function authenticateUser(int $userId): void
    {
        $currentUser = new CurrentUser;
        $currentUser->set([
            'id' => $userId,
            'username' => 'testuser',
            'class' => 1,
        ]);
        $this->app->instance(CurrentUser::class, $currentUser);
    }

    private function insertOffer(int $userId): int
    {
        return (int) DB::table('offers')->insertGetId([
            'userid' => $userId,
            'name' => 'Test Offer',
            'descr' => 'Test description',
            'added' => now()->toDateTimeString(),
            'category' => 1,
            'allowed' => 'pending',
            'yeah' => 0,
            'against' => 0,
            'comments' => 0,
        ]);
    }

    // --- ALLOWED_ACTIONS ---

    public function test_allowed_actions_contains_expected_entries(): void
    {
        $this->assertContains('buyMedal', AjaxService::ALLOWED_ACTIONS);
        $this->assertContains('claimTask', AjaxService::ALLOWED_ACTIONS);
        $this->assertContains('deletePasskey', AjaxService::ALLOWED_ACTIONS);
        $this->assertContains('getOffer', AjaxService::ALLOWED_ACTIONS);
        $this->assertContains('shoutboxPost', AjaxService::ALLOWED_ACTIONS);
    }

    public function test_allowed_actions_does_not_contain_arbitrary_method(): void
    {
        $this->assertNotContains('nonExistentAction', AjaxService::ALLOWED_ACTIONS);
    }

    // --- getOffer ---

    public function test_get_offer_returns_array_for_existing_offer(): void
    {
        $userId = $this->createUser();
        $offerId = $this->insertOffer($userId);

        $result = $this->service->getOffer(['id' => $offerId]);

        $this->assertIsArray($result);
        $this->assertSame($offerId, (int) $result['id']);
        $this->assertSame('Test Offer', $result['name']);
    }

    public function test_get_offer_throws_for_nonexistent_offer(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->getOffer(['id' => 99999]);
    }

    // --- approval ---

    public function test_approval_throws_when_torrent_id_missing(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Require torrent_id');

        $this->service->approval(['approval_status' => 'approved']);
    }

    public function test_approval_throws_when_approval_status_missing(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Require approval_status');

        $this->service->approval(['torrent_id' => 1]);
    }

    public function test_approval_delegates_to_torrent_repository(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->torrentRepo->shouldReceive('approval')
            ->with($userId, ['torrent_id' => 1, 'approval_status' => 'approved'])
            ->once()
            ->andReturn(['status' => 'ok']);

        $result = $this->service->approval(['torrent_id' => 1, 'approval_status' => 'approved']);

        $this->assertSame(['status' => 'ok'], $result);
    }

    // --- shoutboxPost ---

    public function test_shoutbox_post_throws_for_empty_text(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message cannot be empty');

        $this->service->shoutboxPost(['text' => '   ']);
    }

    public function test_shoutbox_post_throws_for_too_long_text(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message too long');

        $this->service->shoutboxPost(['text' => str_repeat('x', Shoutbox::MAX_MESSAGE_LENGTH + 1)]);
    }

    public function test_shoutbox_post_succeeds_with_valid_text(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $result = $this->service->shoutboxPost(['text' => 'Hello world']);

        $this->assertTrue($result);
        $this->assertSame(1, DB::table('shoutbox')->count());
    }

    // --- shoutboxEdit ---

    public function test_shoutbox_edit_throws_for_invalid_id(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input');

        $this->service->shoutboxEdit(['id' => 0, 'text' => 'Hello']);
    }

    public function test_shoutbox_edit_throws_for_empty_text(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input');

        $this->service->shoutboxEdit(['id' => 1, 'text' => '   ']);
    }

    public function test_shoutbox_edit_throws_for_too_long_text(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Message too long');

        $this->service->shoutboxEdit(['id' => 1, 'text' => str_repeat('x', Shoutbox::MAX_MESSAGE_LENGTH + 1)]);
    }

    // --- shoutboxDelete ---

    public function test_shoutbox_delete_throws_for_invalid_id(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input');

        $this->service->shoutboxDelete(['id' => 0]);
    }

    public function test_shoutbox_delete_throws_for_negative_id(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->shoutboxDelete(['id' => -1]);
    }

    // --- shoutboxReact ---

    public function test_shoutbox_react_throws_for_null_result(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        // Nonexistent message id → toggleReaction returns null
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid reaction or reacting too often');

        $this->service->shoutboxReact(['id' => 99999, 'reaction' => 'invalid']);
    }

    // --- clearShoutBox ---

    public function test_clear_shout_box_throws_when_no_permission(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        // Non-admin user → clearAll returns false → RuntimeException
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No permission');

        $this->service->clearShoutBox([]);
    }

    // --- addToken ---

    public function test_add_token_throws_for_empty_name(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Name is required');

        $this->service->addToken(['name' => '']);
    }

    public function test_add_token_succeeds_with_valid_name(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $result = $this->service->addToken(['name' => 'My API Token']);

        $this->assertTrue($result);
        $this->assertSame(1, DB::table('personal_access_tokens')->count());
    }

    // --- removeToken ---

    public function test_remove_token_throws_for_empty_id(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('id is required');

        $this->service->removeToken(['id' => '']);
    }

    public function test_remove_token_succeeds_for_existing_token(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        // Create a token first
        $this->service->addToken(['name' => 'Test Token']);
        $tokenId = (int) DB::table('personal_access_tokens')->value('id');

        $result = $this->service->removeToken(['id' => $tokenId]);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    // --- buyMedal ---

    public function test_buy_medal_delegates_to_bonus_repository(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->bonusRepo->shouldReceive('consumeToBuyMedal')
            ->with($userId, 5)
            ->once()
            ->andReturn(true);

        $result = $this->service->buyMedal(['medal_id' => 5]);

        $this->assertTrue($result);
    }

    // --- claimTask ---

    public function test_claim_task_delegates_to_exam_repository(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->examRepo->shouldReceive('assignToUser')
            ->with($userId, 3)
            ->once()
            ->andReturn(true);

        $result = $this->service->claimTask(['exam_id' => 3]);

        $this->assertTrue($result);
    }

    // --- getPasskeyList ---

    public function test_get_passkey_list_delegates_to_passkey_repository(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $expectedList = [['id' => 1, 'name' => 'Key 1']];
        $this->passkeyRepo->shouldReceive('getList')
            ->with($userId)
            ->once()
            ->andReturn($expectedList);

        $result = $this->service->getPasskeyList([]);

        $this->assertSame($expectedList, $result);
    }

    // --- toggleUserMedalStatus ---

    public function test_toggle_user_medal_status_delegates_to_medal_repository(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->medalRepo->shouldReceive('toggleUserMedalStatus')
            ->with(10, $userId)
            ->once()
            ->andReturn(true);

        $result = $this->service->toggleUserMedalStatus(['id' => 10]);

        $this->assertTrue($result);
    }

    // --- attendanceRetroactive ---

    public function test_attendance_retroactive_delegates_to_attendance_repository(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->attendanceRepo->shouldReceive('retroactive')
            ->with($userId, '2024-01-15')
            ->once()
            ->andReturn(true);

        $result = $this->service->attendanceRetroactive(['date' => '2024-01-15']);

        $this->assertTrue($result);
    }

    // --- getPasskeyCreateArgs ---

    public function test_get_passkey_create_args_delegates_to_passkey_repository(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $expectedArgs = ['challenge' => 'abc123'];
        $this->passkeyRepo->shouldReceive('getCreateArgs')
            ->with($userId, 'testuser')
            ->once()
            ->andReturn($expectedArgs);

        $result = $this->service->getPasskeyCreateArgs([]);

        $this->assertSame($expectedArgs, $result);
    }

    // --- deletePasskey ---

    public function test_delete_passkey_delegates_to_passkey_repository(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->passkeyRepo->shouldReceive('delete')
            ->with($userId, 'cred-123')
            ->once()
            ->andReturn(true);

        $result = $this->service->deletePasskey(['credentialId' => 'cred-123']);

        $this->assertTrue($result);
    }

    // --- saveUserMedal ---

    public function test_save_user_medal_parses_params_and_delegates(): void
    {
        $userId = $this->createUser();
        $this->authenticateUser($userId);

        $this->medalRepo->shouldReceive('saveUserMedal')
            ->with($userId, Mockery::on(function ($data): bool {
                return isset($data[1]) && $data[1]['status'] === '1';
            }))
            ->once()
            ->andReturn(true);

        $params = [
            ['name' => 'status_1', 'value' => '1'],
            ['name' => 'invalid', 'value' => 'x'], // missing underscore → skipped
            ['invalid' => 'nope'], // missing name/value → skipped
        ];

        $result = $this->service->saveUserMedal($params); // @phpstan-ignore argument.type

        $this->assertTrue($result);
    }
}
