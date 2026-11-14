<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Models\User;
use App\Models\UserMeta;
use App\Repositories\HitAndRunRepository;
use App\Repositories\UserDetailRepository;
use App\Services\SeedBonusService;
use App\Services\UserDetailPageService;
use App\Support\Settings;
use App\ViewModels\UserDetailPageViewModel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\Concerns\MakesCurrentUser;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Unit tests for UserDetailPageService.
 *
 * Covers build() for guest/owner/staff actors, PM-button branches,
 * ratio and warn fields, metas props and Hit&Run status.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UserDetailPageServiceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesCurrentUser;
    use SeedsLegacySettings;

    /** @var UserRepositoryInterface&MockInterface */
    private UserRepositoryInterface $userRepository;

    /** @var UserDetailRepository&MockInterface */
    private UserDetailRepository $userDetailRepository;

    /** @var HitAndRunRepository&MockInterface */
    private HitAndRunRepository $hitAndRunRepository;

    private int $userId = 0;

    private int $staffId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->seedTestSettings([]);

        $this->userId = $this->insertUser('targetuser', 1);
        $this->staffId = $this->insertUser('staffuser', 15);

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->userDetailRepository = Mockery::mock(UserDetailRepository::class);
        $this->hitAndRunRepository = Mockery::mock(HitAndRunRepository::class);
    }

    private function insertUser(string $name, int $class): int
    {
        return (int) DB::table('users')->insertGetId([
            'username' => $name,
            'email' => $name.'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'class' => $class,
            'added' => now()->subDays(30)->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 1,
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 0.0,
            'avatar' => '',
            'invites' => 0,
            'notifs' => '',
            'privacy' => 1,
            'stylesheet' => 1,
            'acceptpms' => 0,
            'ip' => '',
        ]);
    }

    /**
     * Build the service with a given actor (also registered as the
     * global ActorContext so Permission::can sees the same user).
     *
     * @param  array<string, mixed>  $actorData
     */
    private function makeService(array $actorData = []): UserDetailPageService
    {
        $actor = $this->actor($actorData);
        app()->instance(ActorContext::class, $actor);

        return new UserDetailPageService(
            $this->userRepository,
            $this->userDetailRepository,
            $this->hitAndRunRepository,
            app(SeedBonusService::class),
            $actor,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function baseUser(array $overrides = []): array
    {
        return array_merge([
            'id' => $this->userId,
            'acceptpms' => 'no',
            'added' => '2020-01-01 00:00:00',
            'avatar' => '',
            'class' => 1,
            'country' => 0,
            'donor' => 0,
            'donoruntil' => null,
            'downloaded' => 0,
            'invited_by' => 0,
            'ip' => '',
            'lastwarned' => '',
            'leechtime' => 0,
            'leechwarn' => 'no',
            'leechwarnuntil' => '0000-00-00 00:00:00',
            'seedtime' => 0,
            'timeswarned' => 0,
            'uploaded' => 0,
            'warned' => 'no',
            'warnedby' => '',
            'warneduntil' => '0000-00-00 00:00:00',
        ], $overrides);
    }

    private function stubDetailRepo(): void
    {
        $this->userDetailRepository->allows('getPeers')->andReturn([]);
        $this->userDetailRepository->allows('getTrueTraffic')->andReturn(['uploaded' => 0, 'downloaded' => 0]);
        $this->userDetailRepository->allows('getCommentCount')->andReturn(0);
        $this->userDetailRepository->allows('getPostCount')->andReturn(0);
        $this->userDetailRepository->allows('getModComment')->andReturn('');
        $this->userDetailRepository->allows('getBonusComment')->andReturn('');
        $this->userDetailRepository->allows('isFriend')->andReturn(false);
        $this->userDetailRepository->allows('isBlocked')->andReturn(false);
        $this->userRepository->allows('listMetas')->andReturn(collect());
    }

    public function test_build_returns_view_model_for_guest_actor(): void
    {
        $this->stubDetailRepo();
        $service = $this->makeService();

        $user = $this->baseUser();
        $vm = $service->build($this->userId, $user, null);

        self::assertInstanceOf(UserDetailPageViewModel::class, $vm);
        self::assertSame($user, $vm->user);
        self::assertFalse($vm->isOwner);
        self::assertFalse($vm->isFriend);
        self::assertFalse($vm->canManageConfidential);
        self::assertFalse($vm->showPmButton);
        self::assertSame(0, $vm->torrentcomments);
        self::assertSame(0, $vm->forumposts);
        self::assertSame(0, $vm->temporaryInviteCount);
        self::assertNull($vm->shareRatio);
        self::assertNull($vm->trueRatio);
        self::assertNull($vm->seedLeechRatio);
        self::assertSame('', (string) $vm->hrStatusHtml);
        self::assertNull($vm->clientSelectHtml);
        self::assertSame(0, $vm->ipHistoryCount);
        self::assertSame([], $vm->userProps);
        self::assertSame('', $vm->triggerId);
        self::assertSame('', (string) $vm->invitedByHtml);
        self::assertCount(5, $vm->torrentListSections);
    }

    public function test_build_shows_pm_button_when_target_accepts_pms(): void
    {
        $this->stubDetailRepo();
        $service = $this->makeService();

        $vm = $service->build($this->userId, $this->baseUser(['acceptpms' => 'yes']), null);

        self::assertTrue($vm->showPmButton);
        self::assertIsArray($vm->locationInfo);
        self::assertIsArray($vm->trueTraffic);
    }

    public function test_build_pm_button_hidden_when_target_blocked_actor(): void
    {
        $this->userDetailRepository->allows('isBlocked')->with($this->userId, 77)->andReturn(true);
        $this->stubDetailRepo();
        $service = $this->makeService(['id' => 77, 'class' => 1]);

        $vm = $service->build($this->userId, $this->baseUser(['acceptpms' => 'yes']), null);

        self::assertFalse($vm->showPmButton);
    }

    public function test_build_pm_button_for_friends_only(): void
    {
        $this->userDetailRepository->allows('isFriend')->with($this->userId, 77)->andReturn(true);
        $this->stubDetailRepo();
        $service = $this->makeService(['id' => 77, 'class' => 1]);

        $vm = $service->build($this->userId, $this->baseUser(['acceptpms' => 'friends']), null);

        self::assertTrue($vm->showPmButton);
        self::assertTrue($vm->currentUserIsFriendOfTarget);
    }

    public function test_build_for_owner_marks_owner_and_shows_clients(): void
    {
        Settings::saveBatch('hr', ['mode' => 'global']);
        Settings::resetCache();

        $this->userDetailRepository->allows('getPeers')->andReturn([
            ['agent' => 'uTorrent/3.5', 'ipv4' => '1.2.3.4', 'ipv6' => '::1', 'port' => '6881'],
        ]);
        $this->userDetailRepository->allows('getTemporaryInviteCount')->andReturn(3);
        $this->hitAndRunRepository->allows('getStatusStats')->andReturn('HNR_STATS');
        $this->stubDetailRepo();

        $service = $this->makeService(['id' => $this->userId, 'class' => 1]);
        $userModel = new User;
        $userModel->setRawAttributes(['id' => $this->userId]);

        $vm = $service->build($this->userId, $this->baseUser(), $userModel);

        self::assertTrue($vm->isOwner);
        self::assertNotNull($vm->clientSelectHtml);
        self::assertStringContainsString('uTorrent', (string) $vm->clientSelectHtml);
        self::assertSame('HNR_STATS', (string) $vm->hrStatusHtml);
        self::assertSame(3, $vm->temporaryInviteCount);
        self::assertNotSame('', $vm->joinWeeks);
    }

    public function test_build_for_staff_grants_manage_flags_and_counts(): void
    {
        $this->userDetailRepository->allows('getIplogCount')->andReturn(7);
        $this->stubDetailRepo();
        $service = $this->makeService(['id' => $this->staffId, 'class' => 15]);

        $user = $this->baseUser(['leechwarn' => 'yes', 'leechwarnuntil' => now()->addDay()->toDateTimeString()]);
        $vm = $service->build($this->userId, $user, null);

        self::assertTrue($vm->canManageBasic);
        self::assertTrue($vm->canViewConfidential);
        self::assertTrue($vm->canViewHistory);
        self::assertTrue($vm->canViewInvite);
        self::assertSame(7, $vm->ipHistoryCount);
        self::assertTrue($vm->leechwarn);
        self::assertNotNull($vm->leechwarnUntilPretty);
        self::assertTrue($vm->canRemoveLeechWarn);
        self::assertTrue($vm->showPmButton);
    }

    public function test_build_computes_ratios_and_warn_fields(): void
    {
        $this->userDetailRepository->allows('getTrueTraffic')->andReturn(['uploaded' => 400, 'downloaded' => 100]);
        $this->userDetailRepository->allows('getWarnedBy')->andReturn(['id' => $this->staffId]);
        $this->stubDetailRepo();
        $service = $this->makeService();

        $user = $this->baseUser([
            'downloaded' => 100,
            'uploaded' => 200,
            'leechtime' => 100,
            'seedtime' => 250,
            'warned' => 'yes',
            'warneduntil' => now()->addDay()->toDateTimeString(),
            'timeswarned' => 1,
            'warnedby' => (string) $this->staffId,
            'lastwarned' => now()->subDay()->toDateTimeString(),
        ]);
        $vm = $service->build($this->userId, $user, null);

        self::assertSame(2.0, $vm->shareRatio);
        self::assertSame(4.0, $vm->trueRatio);
        self::assertSame(2.5, $vm->seedLeechRatio);
        self::assertTrue($vm->warned);
        self::assertNotNull($vm->warnedUntilPretty);
        self::assertNotSame('', $vm->elapsedLastWarn);
        self::assertNotNull($vm->warnedByHtml);
    }

    public function test_build_skips_warnedby_lookup_for_system(): void
    {
        $this->stubDetailRepo();
        $this->userDetailRepository->expects('getWarnedBy')->never();
        $service = $this->makeService();

        $user = $this->baseUser(['timeswarned' => 2, 'warnedby' => 'System']);
        $vm = $service->build($this->userId, $user, null);

        self::assertNull($vm->warnedByHtml);
    }

    public function test_build_collects_user_props_from_metas(): void
    {
        $card = new UserMeta;
        $card->setRawAttributes(['meta_key' => UserMeta::META_KEY_CHANGE_USERNAME, 'status' => 0]);
        $rainbow = new UserMeta;
        $rainbow->setRawAttributes([
            'meta_key' => UserMeta::META_KEY_PERSONALIZED_USERNAME,
            'status' => 0,
            'deadline' => now()->addDay()->toDateTimeString(),
        ]);

        $this->userRepository->allows('listMetas')->andReturn(collect([
            UserMeta::META_KEY_CHANGE_USERNAME => collect([$card, $card]),
            UserMeta::META_KEY_PERSONALIZED_USERNAME => collect([$rainbow]),
        ]));
        $this->stubDetailRepo();

        $service = $this->makeService(['id' => $this->userId, 'class' => 1]);
        $vm = $service->build($this->userId, $this->baseUser(), null);

        self::assertCount(2, $vm->userProps);
        self::assertSame('consume-'.UserMeta::META_KEY_CHANGE_USERNAME, $vm->triggerId);
    }

    public function test_build_invited_by_and_avatar_render_html(): void
    {
        $this->stubDetailRepo();
        $service = $this->makeService();

        $user = $this->baseUser([
            'invited_by' => $this->staffId,
            'avatar' => 'https://example.com/a.png',
        ]);
        $vm = $service->build($this->userId, $user, null);

        self::assertNotSame('', (string) $vm->invitedByHtml);
        self::assertNotSame('', (string) $vm->avatarHtml);
        self::assertStringContainsString('a.png', (string) $vm->avatarHtml);
    }
}
