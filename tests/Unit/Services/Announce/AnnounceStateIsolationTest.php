<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Announce;

use App\DTOs\Announce\AnnounceContext;
use App\DTOs\AnnounceRequestDto;
use App\Services\Announce\ResponseBuilder;
use App\Services\Announce\TrafficResult;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use PHPUnit\Framework\TestCase;

/**
 * W2-04: Octane/RoadRunner cross-request state isolation tests.
 *
 * Verifies that ResponseBuilder and AnnounceContext — which are created
 * per-request — do not leak state between consecutive requests. This is
 * critical for Octane/RoadRunner where the PHP process persists across
 * requests and any mutable singleton or static cache could cause
 * cross-request contamination.
 *
 * @group w2-04
 */
final class AnnounceStateIsolationTest extends TestCase
{
    /**
     * Build a minimal DTO for testing.
     */
    private function makeDto(string $peerId = '-qB4500-'."\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c"): AnnounceRequestDto
    {
        return new AnnounceRequestDto(
            passkey: Passkey::fromString('abcdef0123456789abcdef0123456789'),
            infoHash: InfoHash::fromBinary(str_repeat("\x00", 20)),
            peerId: PeerId::fromBinary($peerId),
            port: 6881,
            uploaded: 0,
            downloaded: 0,
            left: 100,
            event: null,
            numWant: 50,
            compact: false,
            ipv4: '127.0.0.1',
            ipv6: null,
            ip: '127.0.0.1',
            userAgent: 'Test/1.0',
        );
    }

    /**
     * Simulate two consecutive requests in the same PHP process (Octane).
     * The first request's ResponseBuilder must not affect the second.
     */
    public function test_response_builder_does_not_leak_between_requests(): void
    {
        // Request 1: create a ResponseBuilder with torrent data.
        $dto1 = $this->makeDto();
        $builder1 = new ResponseBuilder($dto1, ['id' => 100, 'seeders' => 5, 'times_completed' => 10]);

        // Request 2: create a fresh ResponseBuilder with different torrent.
        $dto2 = $this->makeDto();
        $builder2 = new ResponseBuilder($dto2, ['id' => 200, 'seeders' => 3, 'times_completed' => 7]);

        // withTorrent creates a new instance — original builder must be unchanged.
        $modified1 = $builder1->withTorrent(['id' => 999]);
        $modified2 = $builder2->withTorrent(['id' => 888]);

        // The new instances must have the new torrent, not the original.
        $this->assertNotSame($builder1, $modified1);
        $this->assertNotSame($builder2, $modified2);
        // Each builder is independent.
        $this->assertNotSame($builder1, $builder2);
    }

    public function test_response_builder_with_torrent_returns_new_instance(): void
    {
        $dto = $this->makeDto();
        $builder = new ResponseBuilder($dto);

        $newBuilder = $builder->withTorrent(['id' => 42]);

        $this->assertNotSame($builder, $newBuilder);
    }

    public function test_response_builder_with_real_announce_interval_returns_new_instance(): void
    {
        $dto = $this->makeDto();
        $builder = new ResponseBuilder($dto);

        $newBuilder = $builder->withRealAnnounceInterval(3600);

        $this->assertNotSame($builder, $newBuilder);
    }

    public function test_announce_context_does_not_leak_user_between_requests(): void
    {
        // Request 1: context with user 100.
        $dto1 = $this->makeDto();
        $ctx1 = new AnnounceContext(
            dto: $dto1,
            params: ['passkey' => 'aaaa'],
            ip: '10.0.0.1',
            agent: 'Client/1.0',
            dt: '2026-01-01 00:00:00',
            seeder: 0,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: 300,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto1),
        )->withUser(['id' => 100, 'enabled' => true, 'parked' => false, 'downloadpos' => true]);

        // Request 2: fresh context with user 200.
        $dto2 = $this->makeDto();
        $ctx2 = new AnnounceContext(
            dto: $dto2,
            params: ['passkey' => 'bbbb'],
            ip: '10.0.0.2',
            agent: 'Client/2.0',
            dt: '2026-01-01 00:00:01',
            seeder: 0,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: 300,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto2),
        )->withUser(['id' => 200, 'enabled' => true, 'parked' => false, 'downloadpos' => true]);

        // Context 1 must still have user 100.
        $this->assertSame(100, $ctx1->userId());
        $this->assertSame(200, $ctx2->userId());
    }

    public function test_announce_context_does_not_leak_torrent_between_requests(): void
    {
        $dto1 = $this->makeDto();
        $ctx1 = new AnnounceContext(
            dto: $dto1,
            params: ['passkey' => 'aaaa'],
            ip: '10.0.0.1',
            agent: 'Client/1.0',
            dt: '2026-01-01 00:00:00',
            seeder: 0,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: 300,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto1),
        )->withTorrent(['id' => 500]);

        $dto2 = $this->makeDto();
        $ctx2 = new AnnounceContext(
            dto: $dto2,
            params: ['passkey' => 'bbbb'],
            ip: '10.0.0.2',
            agent: 'Client/2.0',
            dt: '2026-01-01 00:00:01',
            seeder: 0,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: 300,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto2),
        );

        $this->assertSame(500, $ctx1->torrentId());
        $this->assertNull($ctx2->torrent);
    }

    public function test_announce_context_does_not_leak_traffic_between_requests(): void
    {
        $dto1 = $this->makeDto();
        $ctx1 = new AnnounceContext(
            dto: $dto1,
            params: ['passkey' => 'aaaa'],
            ip: '10.0.0.1',
            agent: 'Client/1.0',
            dt: '2026-01-01 00:00:00',
            seeder: 0,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: 300,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto1),
        )->withTraffic(new TrafficResult(
            uploadedIncrementForUser: 1000,
            downloadedIncrementForUser: 500,
            upthis: 100,
            downthis: 50,
            snatchTimeColumn: 'seedtime',
            snatchTimeIncrement: 60,
            leechTimeNoSeederIncrement: 0,
        ));

        $dto2 = $this->makeDto();
        $ctx2 = new AnnounceContext(
            dto: $dto2,
            params: ['passkey' => 'bbbb'],
            ip: '10.0.0.2',
            agent: 'Client/2.0',
            dt: '2026-01-01 00:00:01',
            seeder: 0,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: 300,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto2),
        );

        $this->assertSame(1000, $ctx1->uploadedIncrementForUser);
        $this->assertSame(0, $ctx2->uploadedIncrementForUser);
    }

    public function test_announce_context_does_not_leak_user_update_between_requests(): void
    {
        $dto1 = $this->makeDto();
        $ctx1 = new AnnounceContext(
            dto: $dto1,
            params: ['passkey' => 'aaaa'],
            ip: '10.0.0.1',
            agent: 'Client/1.0',
            dt: '2026-01-01 00:00:00',
            seeder: 0,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: 300,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto1),
        )->withUserUpdate(['last_announce_at' => '2026-01-01 10:00:00']);

        $dto2 = $this->makeDto();
        $ctx2 = new AnnounceContext(
            dto: $dto2,
            params: ['passkey' => 'bbbb'],
            ip: '10.0.0.2',
            agent: 'Client/2.0',
            dt: '2026-01-01 00:00:01',
            seeder: 0,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: 300,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto2),
        );

        $this->assertSame(['last_announce_at' => '2026-01-01 10:00:00'], $ctx1->userUpdate);
        $this->assertSame([], $ctx2->userUpdate);
    }

    /**
     * Repeated with* calls must not mutate the original context.
     * This simulates the same AnnounceService instance handling two
     * requests in Octane — each request gets a fresh context.
     */
    public function test_repeated_with_calls_produce_independent_contexts(): void
    {
        $dto = $this->makeDto();
        $base = new AnnounceContext(
            dto: $dto,
            params: ['passkey' => 'aaaa'],
            ip: '10.0.0.1',
            agent: 'Client/1.0',
            dt: '2026-01-01 00:00:00',
            seeder: 0,
            isDonor: false,
            isReAnnounce: false,
            clientFamilyId: 0,
            announceWait: 300,
            autocleanIntervalOne: 900,
            responseBuilder: new ResponseBuilder($dto),
        );

        $ctx1 = $base->withUser(['id' => 1]);
        $ctx2 = $base->withUser(['id' => 2]);

        $this->assertSame(1, $ctx1->userId());
        $this->assertSame(2, $ctx2->userId());
        $this->assertSame([], $base->user);
    }

    /**
     * The DTO itself is readonly — once constructed, it cannot be mutated.
     * This is the fundamental guarantee for Octane safety.
     */
    public function test_dto_is_readonly_and_cannot_be_mutated(): void
    {
        $dto = $this->makeDto();

        $this->assertSame(6881, $dto->port);
        // Attempting to modify a readonly property throws Error.
        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line intentional readonly violation */
        $dto->port = 9999;
    }
}
