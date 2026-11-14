<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Repositories\HitAndRunRepository;
use App\Repositories\UserDetailRepository;
use App\Services\SeedBonusService;
use App\Services\UserDetailPageService;
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
 * Covers build() for a guest actor (no permissions, no friendship/block
 * lookups) against a minimal legacy user array.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UserDetailPageServiceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesCurrentUser;
    use SeedsLegacySettings;

    private UserDetailPageService $service;

    /** @var UserRepositoryInterface&MockInterface */
    private UserRepositoryInterface $userRepository;

    /** @var UserDetailRepository&MockInterface */
    private UserDetailRepository $userDetailRepository;

    /** @var HitAndRunRepository&MockInterface */
    private HitAndRunRepository $hitAndRunRepository;

    private int $userId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->seedTestSettings([]);

        $this->userId = (int) DB::table('users')->insertGetId([
            'username' => 'targetuser',
            'email' => 'target@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'class' => 1,
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

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->userDetailRepository = Mockery::mock(UserDetailRepository::class);
        $this->hitAndRunRepository = Mockery::mock(HitAndRunRepository::class);

        $this->service = new UserDetailPageService(
            $this->userRepository,
            $this->userDetailRepository,
            $this->hitAndRunRepository,
            app(SeedBonusService::class),
            $this->actor([]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function baseUser(): array
    {
        return [
            'id' => $this->userId,
            'acceptpms' => 'no',
            'added' => '2020-01-01 00:00:00',
            'avatar' => '',
            'class' => 1,
            'country' => 0,
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
        ];
    }

    private function stubDetailRepo(): void
    {
        $this->userDetailRepository->allows('getPeers')->andReturn([]);
        $this->userDetailRepository->allows('getTrueTraffic')->andReturn(['uploaded' => 0, 'downloaded' => 0]);
        $this->userDetailRepository->allows('getCommentCount')->andReturn(0);
        $this->userDetailRepository->allows('getPostCount')->andReturn(0);
        $this->userDetailRepository->allows('getModComment')->andReturn('');
        $this->userDetailRepository->allows('getBonusComment')->andReturn('');
        $this->userRepository->allows('listMetas')->andReturn(collect());
    }

    public function test_build_returns_view_model_for_guest_actor(): void
    {
        $this->stubDetailRepo();

        $user = $this->baseUser();
        $vm = $this->service->build($this->userId, $user, null);

        self::assertInstanceOf(UserDetailPageViewModel::class, $vm);
        self::assertSame($user, $vm->user);
        self::assertFalse($vm->isOwner);
        self::assertFalse($vm->isFriend);
        self::assertFalse($vm->currentUserBlockedTarget);
        self::assertFalse($vm->canManageConfidential);
        self::assertFalse($vm->showPmButton);
        self::assertSame(0, $vm->torrentcomments);
        self::assertSame(0, $vm->forumposts);
        self::assertSame(0, $vm->temporaryInviteCount);
        self::assertSame('', $vm->modcomment);
        self::assertSame('', $vm->bonuscomment);
        self::assertNull($vm->shareRatio);
        self::assertSame('', (string) $vm->hrStatusHtml);
    }

    public function test_build_shows_pm_button_when_target_accepts_pms(): void
    {
        $this->stubDetailRepo();

        $user = $this->baseUser();
        $user['acceptpms'] = 'yes';
        $vm = $this->service->build($this->userId, $user, null);

        self::assertTrue($vm->showPmButton);
        self::assertIsArray($vm->locationInfo);
        self::assertIsArray($vm->trueTraffic);
    }
}
