<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs\Announce;

use App\DTOs\Announce\AnnounceContext;
use App\DTOs\AnnounceRequestDto;
use App\Services\Announce\ResponseBuilder;
use App\Services\Announce\TrafficResult;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class AnnounceContextTest extends TestCase
{
    public function test_context_is_immutable_with_methods_return_new_instances(): void
    {
        $ctx = $this->makeContext();

        $newCtx = $ctx->withUser(['id' => 42, 'enabled' => true, 'parked' => false, 'downloadpos' => true]);

        $this->assertNotSame($ctx, $newCtx);
        $this->assertSame([], $ctx->user);
        $this->assertSame(42, $newCtx->userId());
        $this->assertSame($ctx->dto, $newCtx->dto);
    }

    public function test_with_torrent_returns_new_instance_with_torrent(): void
    {
        $ctx = $this->makeContext();
        $torrent = ['id' => 99, 'size' => 1024];

        $newCtx = $ctx->withTorrent($torrent);

        $this->assertNull($ctx->torrent);
        $this->assertSame($torrent, $newCtx->torrent);
        $this->assertSame(99, $newCtx->torrentId());
    }

    public function test_with_self_and_snatch_info_preserve_other_fields(): void
    {
        $ctx = $this->makeContext()->withUser(['id' => 5]);
        $self = ['id' => 1, 'seeder' => 0];
        $snatchInfo = ['id' => 10, 'uploaded' => 500];

        $newCtx = $ctx->withSelf($self)->withSnatchInfo($snatchInfo);

        $this->assertSame($self, $newCtx->self);
        $this->assertSame($snatchInfo, $newCtx->snatchInfo);
        $this->assertSame(5, $newCtx->userId());
    }

    public function test_with_traffic_sets_traffic_and_increments(): void
    {
        $ctx = $this->makeContext();
        $traffic = new TrafficResult(
            uploadedIncrementForUser: 1000,
            downloadedIncrementForUser: 500,
            upthis: 100,
            downthis: 50,
            snatchTimeColumn: 'seedtime',
            snatchTimeIncrement: 60,
            leechTimeNoSeederIncrement: 0,
        );

        $newCtx = $ctx->withTraffic($traffic);

        $this->assertSame(1000, $newCtx->uploadedIncrementForUser);
        $this->assertSame(500, $newCtx->downloadedIncrementForUser);
        $this->assertSame($traffic, $newCtx->traffic);
    }

    public function test_with_response_builder_preserves_user_and_torrent(): void
    {
        $dto = $this->makeDto();
        $builder = new ResponseBuilder($dto);
        $ctx = $this->makeContext()
            ->withUser(['id' => 7])
            ->withTorrent(['id' => 3]);

        $newCtx = $ctx->withResponseBuilder($builder);

        $this->assertSame($builder, $newCtx->responseBuilder);
        $this->assertSame(7, $newCtx->userId());
        $this->assertSame(3, $newCtx->torrentId());
    }

    public function test_is_seeder_returns_true_when_seeder_is_one(): void
    {
        $ctx = $this->makeContext()->withUser(['id' => 1]);

        $this->assertFalse($ctx->isSeeder());

        $dto = $this->makeDto();
        $seederCtx = new AnnounceContext(
            dto: $dto,
            params: ['passkey' => 'abcdef0123456789abcdef0123456789'],
            ip: '127.0.0.1',
            agent: 'qBittorrent/4.5.2',
            dt: '2026-01-01 00:00:00',
            seeder: 1,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: 300,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto),
        );

        $this->assertTrue($seederCtx->isSeeder());
    }

    public function test_with_user_update_merges_not_replaces(): void
    {
        $ctx = $this->makeContext()->withUserUpdate(['showclienterror' => false]);

        $newCtx = $ctx->withUserUpdate(['last_announce_at' => '2026-01-01 00:00:00']);

        $this->assertSame(['last_announce_at' => '2026-01-01 00:00:00'], $newCtx->userUpdate);
        $this->assertSame(['showclienterror' => false], $ctx->userUpdate);
    }

    private function makeContext(): AnnounceContext
    {
        $dto = $this->makeDto();

        return new AnnounceContext(
            dto: $dto,
            params: ['passkey' => 'abcdef0123456789abcdef0123456789'],
            ip: '127.0.0.1',
            agent: 'qBittorrent/4.5.2',
            dt: '2026-01-01 00:00:00',
            seeder: 0,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: 300,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto),
        );
    }

    private function makeDto(): AnnounceRequestDto
    {
        $request = Request::create('/announce', 'GET', [
            'passkey' => 'abcdef0123456789abcdef0123456789',
            'info_hash' => str_repeat("\x00", 20),
            'peer_id' => '-qB4'.str_repeat("\x01", 16),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 100,
        ]);

        $params = [
            'passkey' => 'abcdef0123456789abcdef0123456789',
            'info_hash' => str_repeat("\x00", 20),
            'peer_id' => '-qB4'.str_repeat("\x01", 16),
            'port' => 6881,
            'uploaded' => 0,
            'downloaded' => 0,
            'left' => 100,
        ];

        return AnnounceRequestDto::fromRequest($request, $params);
    }
}
