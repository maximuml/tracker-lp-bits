<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Announce;

use App\Contracts\Repositories\UserModerationRepositoryInterface;
use App\DTOs\Announce\AnnounceContext;
use App\DTOs\AnnounceRequestDto;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TorrentPurchaseRepository;
use App\Services\Announce\ResponseBuilder;
use App\Services\Announce\TorrentGate;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
        $user = User::factory()->create(['class' => 1]);
        $ctx = $this->makeCtx($this->makeDto(str_repeat("\xcd", 20)), $user);

        $this->expectException(TrackerException::class);
        $this->expectExceptionMessage('torrent not registered');
        $this->gate->resolve($ctx);
    }

    public function test_resolve_returns_ctx_with_torrent(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->owner($user)->create(['size' => 10000, 'banned' => 0]);
        $ctx = $this->makeCtx($this->makeDto($torrent->info_hash), $user);

        $resolved = $this->gate->resolve($ctx);

        $this->assertNotNull($resolved->torrent);
        $this->assertSame($torrent->id, (int) $resolved->torrent['id']);
    }

    public function test_resolve_throws_for_banned_torrent(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->owner($user)->create(['size' => 10000, 'banned' => 1]);
        $ctx = $this->makeCtx($this->makeDto($torrent->info_hash), $user);

        $this->expectException(TrackerException::class);
        $this->expectExceptionMessage('torrent banned');
        $this->gate->resolve($ctx);
    }

    public function test_resolve_warns_on_fake_announce(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->owner($user)->create(['size' => 100, 'banned' => 0]);
        // left (500) > size (100) triggers the fake-announce guard.
        $ctx = $this->makeCtx($this->makeDto($torrent->info_hash, null, 500), $user);

        $this->expectException(TrackerWarningException::class);
        $this->gate->resolve($ctx);
    }

    public function test_check_paid_skips_seeders(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->owner($user)->create(['size' => 10000, 'price' => 50]);
        $ctx = $this->makeCtx($this->makeDto($torrent->info_hash), $user, seeder: 1);
        $ctx = $ctx->withTorrent($this->torrentRow($torrent));

        $this->gate->checkPaid($ctx);
        $this->addToAssertionCount(1);
    }

    public function test_check_paid_skips_free_torrents(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $other = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->owner($other)->create(['size' => 10000, 'price' => 0]);
        $ctx = $this->makeCtx($this->makeDto($torrent->info_hash), $user);
        $ctx = $ctx->withTorrent($this->torrentRow($torrent));

        $this->gate->checkPaid($ctx);
        $this->addToAssertionCount(1);
    }

    public function test_check_paid_skips_owner(): void
    {
        $user = User::factory()->create(['class' => 1]);
        $torrent = Torrent::factory()->owner($user)->create(['size' => 10000, 'price' => 50]);
        $ctx = $this->makeCtx($this->makeDto($torrent->info_hash), $user);
        $ctx = $ctx->withTorrent($this->torrentRow($torrent));

        $this->gate->checkPaid($ctx);
        $this->addToAssertionCount(1);
    }

    /** @return array<string, mixed> */
    private function torrentRow(Torrent $torrent): array
    {
        $row = $torrent->getAttributes();
        $row['ts'] = time();

        return $row;
    }
}
