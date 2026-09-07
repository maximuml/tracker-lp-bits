<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\AnnounceRequestDto;
use App\Services\Announce\AnnounceRequestFactory;
use App\Support\Network;
use App\Support\Network\ClientIpResolver;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class AnnounceRequestDtoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Network::setTrustedProxies([]);
    }

    protected function tearDown(): void
    {
        Network::setTrustedProxies(null);
        parent::tearDown();
    }

    public function test_build_parses_all_fields(): void
    {
        $passkey = str_repeat('a', 32);
        $infoHash = str_repeat("\x00", 20);
        $peerId = str_repeat("\x01", 20);

        $request = Request::create('/announce.php', 'GET', [
            'passkey' => $passkey,
            'info_hash' => $infoHash,
            'peer_id' => $peerId,
            'port' => '51413',
            'uploaded' => '1024',
            'downloaded' => '2048',
            'left' => '0',
            'event' => 'completed',
            'numwant' => '100',
            'compact' => '1',
            'ipv4' => '1.2.3.4',
            'ipv6' => '2001:db8::1',
        ], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_USER_AGENT' => 'PHPUnit/TestClient',
        ]);
        app()->instance('request', $request);

        $factory = new AnnounceRequestFactory(new ClientIpResolver);
        $dto = $factory->create($request, $request->query->all());

        $this->assertSame($passkey, $dto->passkey->toString());
        $this->assertSame($infoHash, $dto->infoHash->toBinary());
        $this->assertSame($peerId, $dto->peerId->toBinary());
        $this->assertSame(51413, $dto->port);
        $this->assertSame(1024, $dto->uploaded);
        $this->assertSame(2048, $dto->downloaded);
        $this->assertSame(0, $dto->left);
        $this->assertSame('completed', $dto->event);
        $this->assertSame(100, $dto->numWant);
        $this->assertTrue($dto->compact);
        $this->assertSame('10.0.0.1', $dto->ipv4);
        $this->assertSame('2001:db8::1', $dto->ipv6);
        $this->assertSame('10.0.0.1', $dto->ip);
        $this->assertSame('PHPUnit/TestClient', $dto->userAgent);
        $this->assertTrue($dto->isSeeder());
        $this->assertTrue($dto->isCompleted());
        $this->assertTrue($dto->isStoppedOrCompleted());
    }

    public function test_invalid_event_is_normalized_to_null(): void
    {
        $request = Request::create('/announce.php', 'GET', [
            'passkey' => str_repeat('a', 32),
            'info_hash' => str_repeat("\x00", 20),
            'peer_id' => str_repeat("\x01", 20),
            'port' => '1234',
            'uploaded' => '0',
            'downloaded' => '0',
            'left' => '100',
            'event' => 'invalid_event',
        ], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        app()->instance('request', $request);

        $factory = new AnnounceRequestFactory(new ClientIpResolver);
        $dto = $factory->create($request, $request->query->all());

        $this->assertNull($dto->event);
        $this->assertFalse($dto->isSeeder());
        $this->assertFalse($dto->isCompleted());
    }

    public function test_numwant_is_clamped_to_maximum(): void
    {
        $request = Request::create('/announce.php', 'GET', [
            'passkey' => str_repeat('a', 32),
            'info_hash' => str_repeat("\x00", 20),
            'peer_id' => str_repeat("\x01", 20),
            'port' => '1234',
            'uploaded' => '0',
            'downloaded' => '0',
            'left' => '100',
            'numwant' => '500',
        ], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        app()->instance('request', $request);

        $factory = new AnnounceRequestFactory(new ClientIpResolver);
        $dto = $factory->create($request, $request->query->all());

        $this->assertSame(200, $dto->numWant);
    }

    public function test_to_params_returns_original_shape(): void
    {
        $passkey = str_repeat('a', 32);
        $infoHash = str_repeat("\x00", 20);
        $peerId = str_repeat("\x01", 20);

        $request = Request::create('/announce.php', 'GET', [
            'passkey' => $passkey,
            'info_hash' => $infoHash,
            'peer_id' => $peerId,
            'port' => '1234',
            'uploaded' => '0',
            'downloaded' => '0',
            'left' => '100',
        ], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
        ]);
        app()->instance('request', $request);

        $factory = new AnnounceRequestFactory(new ClientIpResolver);
        $dto = $factory->create($request, $request->query->all());
        $params = $dto->toParams();

        $this->assertSame($passkey, $params['passkey']);
        $this->assertSame($infoHash, $params['info_hash']);
        $this->assertSame($peerId, $params['peer_id']);
        $this->assertArrayHasKey('ip', $params);
        $this->assertSame($dto->ip, $params['ip']);
    }

    public function test_dto_is_pure_data_container_without_side_effects(): void
    {
        // W2-03: The DTO constructor does not touch global state.
        // It can be created directly without a request or container.
        $dto = new AnnounceRequestDto(
            passkey: Passkey::fromString(str_repeat('a', 32)),
            infoHash: InfoHash::fromBinary(str_repeat("\x00", 20)),
            peerId: PeerId::fromBinary(str_repeat("\x01", 20)),
            port: 6881,
            uploaded: 0,
            downloaded: 0,
            left: 100,
            event: null,
            numWant: 50,
            compact: false,
            ipv4: '10.0.0.1',
            ipv6: null,
            ip: '10.0.0.1',
            userAgent: 'Test/1.0',
        );

        $this->assertSame(6881, $dto->port);
        $this->assertFalse($dto->isSeeder());
        $this->assertFalse($dto->isStopped());
    }
}
