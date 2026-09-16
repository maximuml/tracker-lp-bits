<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Announce;

use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\DTOs\Announce\AnnounceContext;
use App\DTOs\AnnounceRequestDto;
use App\Enums\TorrentApprovalStatus;
use App\Enums\UserClass as UserClassEnum;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Jobs\BuyTorrent;
use App\Models\Torrent;
use App\Models\TorrentBuyLog;
use App\Models\User;
use App\Repositories\TorrentPurchaseRepository;
use App\Services\Announce\ResponseBuilder;
use App\Services\Announce\TorrentGate;
use App\Support\Settings;
use App\Utils\MsgAlert;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use ReflectionProperty;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * TorrentGate: torrent resolution guards and the paid-torrent gate.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TorrentGateTest extends TestCase
{
    use DatabaseTransactions;

    private TorrentGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        defined('TIMENOW') || define('TIMENOW', time());
        $this->gate = new TorrentGate(
            app(TorrentPurchaseRepository::class),
            app(UserModerationRepositoryInterface::class),
        );
    }

    protected function tearDown(): void
    {
        Settings::resetCache();
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function makeUser(array $attributes = ['class' => 1]): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributes);

        return $user;
    }

    /** @param array<string, mixed> $attributes */
    private function makeTorrent(User $owner, array $attributes = []): Torrent
    {
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->owner($owner)->create($attributes + ['size' => 10000]);

        return $torrent;
    }

    private function setSetting(string $name, string $value): void
    {
        DB::table('settings')->updateOrInsert(['name' => $name], ['value' => $value, 'autoload' => 1]);
        Settings::resetCache();
    }

    private function makeDto(string $infoHashBinary, ?string $event = null, int $left = 500): AnnounceRequestDto
    {
        return new AnnounceRequestDto(
            passkey: Passkey::fromString(str_repeat('a', 32)),
            infoHash: InfoHash::fromBinary($infoHashBinary),
            peerId: PeerId::fromBinary('-qB4500-'.str_repeat("\x01", 12)),
            port: 6881,
            uploaded: 100,
            downloaded: 50,
            left: $left,
            event: $event,
            numWant: 50,
            compact: false,
            ipv4: '10.0.0.1',
            ipv6: null,
            ip: '10.0.0.1',
            userAgent: 'qBittorrent/4.5.0',
        );
    }

    private function makeCtx(AnnounceRequestDto $dto, User $user, int $seeder = 0): AnnounceContext
    {
        return new AnnounceContext(
            dto: $dto,
            params: $dto->toParams(),
            ip: $dto->ip,
            agent: $dto->userAgent,
            dt: date('Y-m-d H:i:s'),
            seeder: $seeder,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: 300,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto),
            user: $user->getAttributes(),
        );
    }

    public function test_resolve_throws_for_unknown_info_hash(): void
    {
        $user = $this->makeUser();
        $ctx = $this->makeCtx($this->makeDto(str_repeat("\xcd", 20)), $user);

        $this->expectException(TrackerException::class);
        $this->expectExceptionMessage('torrent not registered');
        $this->gate->resolve($ctx);
    }

    public function test_resolve_returns_ctx_with_torrent(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user, ['banned' => 0]);
        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $user);

        $resolved = $this->gate->resolve($ctx);

        $this->assertNotNull($resolved->torrent);
        $this->assertSame($torrent->id, (int) $resolved->torrent['id']);
    }

    public function test_resolve_throws_for_banned_torrent(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user, ['banned' => 1]);
        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $user);

        $this->expectException(TrackerException::class);
        $this->expectExceptionMessage('torrent banned');
        $this->gate->resolve($ctx);
    }

    public function test_resolve_warns_on_fake_announce(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user, ['size' => 100, 'banned' => 0]);
        // left (500) > size (100) triggers the fake-announce guard.
        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash, null, 500), $user);

        $this->expectException(TrackerWarningException::class);
        $this->gate->resolve($ctx);
    }

    public function test_check_paid_skips_seeders(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user, ['price' => 50]);
        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $user, seeder: 1);
        $ctx = $ctx->withTorrent($this->torrentRow($torrent));

        $this->gate->checkPaid($ctx);
        $this->addToAssertionCount(1);
    }

    public function test_check_paid_skips_free_torrents(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $torrent = $this->makeTorrent($other, ['price' => 0]);
        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $user);
        $ctx = $ctx->withTorrent($this->torrentRow($torrent));

        $this->gate->checkPaid($ctx);
        $this->addToAssertionCount(1);
    }

    public function test_check_paid_skips_owner(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user, ['price' => 50]);
        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $user);
        $ctx = $ctx->withTorrent($this->torrentRow($torrent));

        $this->gate->checkPaid($ctx);
        $this->addToAssertionCount(1);
    }

    public function test_resolve_caches_found_torrent_row(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user, ['banned' => 0]);
        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $user);

        $this->gate->resolve($ctx);

        $cached = Cache::get("torrent_hash_{$torrent->info_hash}_content");
        $this->assertIsArray($cached);
        $this->assertSame($torrent->id, (int) $cached['id']);
    }

    public function test_resolve_flags_unknown_torrent_in_redis_with_ttl(): void
    {
        $user = $this->makeUser();
        $hash = str_repeat("\xcd", 20);
        $ctx = $this->makeCtx($this->makeDto($hash), $user);

        try {
            $this->gate->resolve($ctx);
            $this->fail('expected TrackerException');
        } catch (TrackerException $e) {
            $this->assertSame('torrent not registered with this tracker', $e->getMessage());
        }

        $client = Redis::connection()->client();
        $this->assertNotFalse($client->get('torrent_not_exists:'.$hash));
        $this->assertSame(86400, (int) $client->ttl('torrent_not_exists:'.$hash));
    }

    public function test_resolve_allows_banned_torrent_for_staff(): void
    {
        $staff = $this->makeUser(['class' => UserClassEnum::STAFFLEADER->value]);
        $torrent = $this->makeTorrent($staff, ['banned' => 1]);
        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $staff);

        $resolved = $this->gate->resolve($ctx);
        $this->assertNotNull($resolved->torrent);
        $this->assertSame($torrent->id, (int) $resolved->torrent['id']);
    }

    public function test_resolve_rejects_unapproved_torrent_when_none_hidden(): void
    {
        $this->setSetting('torrent.approval_status_none_visible', 'no');

        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user, ['banned' => 0, 'approval_status' => TorrentApprovalStatus::NONE->value]);
        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $user);

        $this->expectException(TrackerException::class);
        $this->expectExceptionMessage('torrent review not approved');
        $this->gate->resolve($ctx);
    }

    public function test_resolve_allows_approved_torrent_when_none_hidden(): void
    {
        $this->setSetting('torrent.approval_status_none_visible', 'no');

        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user, ['banned' => 0, 'approval_status' => TorrentApprovalStatus::ALLOW->value]);
        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $user);

        $resolved = $this->gate->resolve($ctx);
        $this->assertNotNull($resolved->torrent);
        $this->assertSame($torrent->id, (int) $resolved->torrent['id']);
    }

    public function test_resolve_allows_left_equal_to_size(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user, ['size' => 500, 'banned' => 0]);
        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash, null, 500), $user);

        $this->gate->resolve($ctx);
        $this->addToAssertionCount(1);
    }

    public function test_resolve_fake_announce_disables_download_and_warns(): void
    {
        $user = $this->makeUser();
        $torrent = $this->makeTorrent($user, ['size' => 100, 'banned' => 0]);

        /** @var UserModerationRepositoryInterface&Mockery\MockInterface $moderation */
        $moderation = Mockery::mock(UserModerationRepositoryInterface::class);
        $moderation->shouldReceive('updateDownloadPrivileges')
            ->once()
            ->with(null, $user->id, false, 'fake_announce');
        $gate = new TorrentGate(app(TorrentPurchaseRepository::class), $moderation);

        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash, null, 500), $user);

        try {
            $gate->resolve($ctx);
            $this->fail('expected TrackerWarningException');
        } catch (TrackerWarningException $e) {
            $this->assertSame(300, $e->getResponse()['interval']);
        }
    }

    public function test_check_paid_warns_and_dispatches_when_status_unknown(): void
    {
        Bus::fake();
        $this->setSetting('torrent.paid_torrent_enabled', 'yes');

        $buyer = $this->makeUser(['class' => 1, 'seedbonus' => 100]);
        $owner = $this->makeUser();
        $torrent = $this->makeTorrent($owner, ['price' => 50]);
        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $buyer);
        $ctx = $ctx->withTorrent($this->torrentRow($torrent));

        try {
            $this->gate->checkPaid($ctx);
            $this->fail('expected TrackerWarningException');
        } catch (TrackerWarningException $e) {
            $this->assertStringContainsString('purchase started', $e->getMessage());
        }

        Bus::assertDispatched(BuyTorrent::class);
    }

    public function test_check_paid_passes_after_successful_purchase(): void
    {
        Bus::fake();
        $this->setSetting('torrent.paid_torrent_enabled', 'yes');

        $buyer = $this->makeUser(['class' => 1, 'seedbonus' => 100]);
        $owner = $this->makeUser();
        $torrent = $this->makeTorrent($owner, ['price' => 50]);
        TorrentBuyLog::create(['uid' => $buyer->id, 'torrent_id' => $torrent->id, 'price' => 50, 'channel' => 'test']);

        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $buyer);
        $ctx = $ctx->withTorrent($this->torrentRow($torrent));

        $this->gate->checkPaid($ctx);
        $this->addToAssertionCount(1);
        Bus::assertNotDispatched(BuyTorrent::class);
    }

    public function test_check_paid_warns_during_fail_cooldown(): void
    {
        Bus::fake();
        $this->setSetting('torrent.paid_torrent_enabled', 'yes');

        $buyer = $this->makeUser(['class' => 1, 'seedbonus' => 100]);
        $owner = $this->makeUser();
        $torrent = $this->makeTorrent($owner, ['price' => 50]);
        app(TorrentPurchaseRepository::class)->addBuyFailCache($buyer->id, $torrent->id);

        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $buyer);
        $ctx = $ctx->withTorrent($this->torrentRow($torrent));

        try {
            $this->gate->checkPaid($ctx);
            $this->fail('expected TrackerWarningException');
        } catch (TrackerWarningException $e) {
            $this->assertStringContainsString('purchase in progress', $e->getMessage());
        }

        Bus::assertDispatched(BuyTorrent::class);
    }

    public function test_check_paid_adds_alert_after_three_failures(): void
    {
        Bus::fake();
        $this->setSetting('torrent.paid_torrent_enabled', 'yes');
        (new ReflectionProperty(MsgAlert::class, 'alerts'))->setValue(null, []);

        $buyer = $this->makeUser(['class' => 1, 'seedbonus' => 100]);
        $owner = $this->makeUser();
        $torrent = $this->makeTorrent($owner, ['price' => 50]);
        $repo = app(TorrentPurchaseRepository::class);
        for ($i = 0; $i < 4; $i++) {
            $repo->addBuyFailCache($buyer->id, $torrent->id);
        }

        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $buyer);
        $ctx = $ctx->withTorrent($this->torrentRow($torrent));

        try {
            $this->gate->checkPaid($ctx);
            $this->fail('expected TrackerWarningException');
        } catch (TrackerWarningException) {
            $items = Redis::connection()->client()->lRange('nexus_alerts:0', 0, -1);
            $names = array_map(static fn ($item) => (string) $item, $items);
            $this->assertNotEmpty($names);
            $this->assertStringContainsString(
                'announce_paid_torrent_too_many_times',
                implode("\n", $names)
            );
        }
    }

    public function test_check_paid_disables_downloads_after_ten_failures(): void
    {
        Bus::fake();
        $this->setSetting('torrent.paid_torrent_enabled', 'yes');
        (new ReflectionProperty(MsgAlert::class, 'alerts'))->setValue(null, []);

        $buyer = $this->makeUser(['class' => 1, 'seedbonus' => 100]);
        $owner = $this->makeUser();
        $torrent = $this->makeTorrent($owner, ['price' => 50]);
        $repo = app(TorrentPurchaseRepository::class);
        for ($i = 0; $i < 11; $i++) {
            $repo->addBuyFailCache($buyer->id, $torrent->id);
        }

        /** @var UserModerationRepositoryInterface&Mockery\MockInterface $moderation */
        $moderation = Mockery::mock(UserModerationRepositoryInterface::class);
        $moderation->shouldReceive('updateDownloadPrivileges')
            ->once()
            ->with(null, $buyer->id, false, 'announce_paid_torrent_too_many_times');
        $gate = new TorrentGate($repo, $moderation);

        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $buyer);
        $ctx = $ctx->withTorrent($this->torrentRow($torrent));

        $this->expectException(TrackerWarningException::class);
        $gate->checkPaid($ctx);
    }

    public function test_check_paid_keeps_downloads_at_ten_failures(): void
    {
        Bus::fake();
        $this->setSetting('torrent.paid_torrent_enabled', 'yes');
        (new ReflectionProperty(MsgAlert::class, 'alerts'))->setValue(null, []);

        $buyer = $this->makeUser(['class' => 1, 'seedbonus' => 100]);
        $owner = $this->makeUser();
        $torrent = $this->makeTorrent($owner, ['price' => 50]);
        $repo = app(TorrentPurchaseRepository::class);
        for ($i = 0; $i < 10; $i++) {
            $repo->addBuyFailCache($buyer->id, $torrent->id);
        }

        /** @var UserModerationRepositoryInterface&Mockery\MockInterface $moderation */
        $moderation = Mockery::mock(UserModerationRepositoryInterface::class);
        $moderation->shouldNotReceive('updateDownloadPrivileges');
        $gate = new TorrentGate($repo, $moderation);

        $ctx = $this->makeCtx($this->makeDto((string) $torrent->info_hash), $buyer);
        $ctx = $ctx->withTorrent($this->torrentRow($torrent));

        $this->expectException(TrackerWarningException::class);
        $gate->checkPaid($ctx);
    }

    /** @return array<string, mixed> */
    private function torrentRow(Torrent $torrent): array
    {
        $row = $torrent->getAttributes();
        $row['ts'] = time();

        return $row;
    }
}
