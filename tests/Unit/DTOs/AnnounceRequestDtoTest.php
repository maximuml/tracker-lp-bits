<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\AnnounceRequestDto;
use App\Services\Announce\AnnounceRequestFactory;
use App\Support\Network\ClientIpResolver;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use PHPUnit\Framework\TestCase;

/**
 * W2-03: AnnounceRequestDto and AnnounceRequestFactory tests.
 *
 * These tests run WITHOUT booting the Laravel container — the parser
 * is tested in isolation, per the W2-03 acceptance criteria:
 *   "parser тестируется без Laravel application"
 *
 * The factory's build() method is pure (no global state); only create()
 * has the SupportContext::fromRequest() side effect, which is not tested
 * here but in the integration tests.
 */
final class AnnounceRequestDtoTest extends TestCase
{
    private const PASSKEY = 'abcdef0123456789abcdef0123456789';

    private const INFO_HASH = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13";

    private const PEER_ID = '-qB4500-'."\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c";

    /**
     * Build params array for a standard announce request.
     *
     * @return array<string, mixed>
     */
    private function standardParams(array $overrides = []): array
    {
        return array_merge([
            'passkey' => self::PASSKEY,
            'info_hash' => self::INFO_HASH,
            'peer_id' => self::PEER_ID,
            'port' => '51413',
            'uploaded' => '1024',
            'downloaded' => '2048',
            'left' => '0',
            'event' => 'completed',
            'numwant' => '100',
            'compact' => '1',
            'ipv4' => '1.2.3.4',
            'ipv6' => '2001:db8::1',
        ], $overrides);
    }

    /**
     * Create a factory with a mocked ClientIpResolver that returns a fixed IP.
     */
    private function makeFactory(string $resolvedIp = '10.0.0.1'): AnnounceRequestFactory
    {
        $resolver = $this->createStub(ClientIpResolver::class);
        $resolver->method('resolve')->willReturn($resolvedIp);

        return new AnnounceRequestFactory($resolver);
    }

    public function test_build_parses_all_fields(): void
    {
        // Resolver returns IPv6 so the param ipv4 is used as the secondary address.
        $factory = $this->makeFactory('2001:db8::1');
        $dto = $factory->build($this->standardParams(), 'PHPUnit/TestClient');

        $this->assertSame(self::PASSKEY, $dto->passkey->toString());
        $this->assertSame(self::INFO_HASH, $dto->infoHash->toBinary());
        $this->assertSame(self::PEER_ID, $dto->peerId->toBinary());
        $this->assertSame(51413, $dto->port);
        $this->assertSame(1024, $dto->uploaded);
        $this->assertSame(2048, $dto->downloaded);
        $this->assertSame(0, $dto->left);
        $this->assertSame('completed', $dto->event);
        $this->assertSame(100, $dto->numWant);
        $this->assertTrue($dto->compact);
        $this->assertSame('1.2.3.4', $dto->ipv4);
        $this->assertSame('2001:db8::1', $dto->ipv6);
        $this->assertSame('2001:db8::1', $dto->ip);
        $this->assertSame('PHPUnit/TestClient', $dto->userAgent);
        $this->assertTrue($dto->isSeeder());
        $this->assertTrue($dto->isCompleted());
        $this->assertTrue($dto->isStoppedOrCompleted());
    }

    public function test_invalid_event_is_normalized_to_null(): void
    {
        $factory = $this->makeFactory();
        $dto = $factory->build($this->standardParams(['event' => 'invalid_event', 'left' => '100']));

        $this->assertNull($dto->event);
        $this->assertFalse($dto->isSeeder());
        $this->assertFalse($dto->isCompleted());
    }

    public function test_numwant_is_clamped_to_maximum(): void
    {
        $factory = $this->makeFactory();
        $dto = $factory->build($this->standardParams(['numwant' => '500']));

        $this->assertSame(200, $dto->numWant);
    }

    public function test_numwant_negative_is_clamped_to_zero(): void
    {
        $factory = $this->makeFactory();
        $dto = $factory->build($this->standardParams(['numwant' => '-10']));

        $this->assertSame(0, $dto->numWant);
    }

    public function test_to_params_returns_original_shape(): void
    {
        $factory = $this->makeFactory('10.0.0.1');
        $dto = $factory->build($this->standardParams(), 'Test/1.0');
        $params = $dto->toParams();

        $this->assertSame(self::PASSKEY, $params['passkey']);
        $this->assertSame(self::INFO_HASH, $params['info_hash']);
        $this->assertSame(self::PEER_ID, $params['peer_id']);
        $this->assertArrayHasKey('ip', $params);
        $this->assertSame('10.0.0.1', $params['ip']);
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

    // ─── W2-03: IPv4/IPv6 and trusted proxy tests ─────────────────────

    public function test_ipv4_resolved_from_resolver_when_no_param(): void
    {
        $factory = $this->makeFactory('192.168.1.50');
        $dto = $factory->build($this->standardParams(['ipv4' => null, 'ipv6' => null]));

        $this->assertSame('192.168.1.50', $dto->ip);
        $this->assertSame('192.168.1.50', $dto->ipv4);
        $this->assertNull($dto->ipv6);
    }

    public function test_ipv6_resolved_from_resolver_when_no_param(): void
    {
        $factory = $this->makeFactory('2001:db8::42');
        $dto = $factory->build($this->standardParams(['ipv4' => null, 'ipv6' => null]));

        $this->assertSame('2001:db8::42', $dto->ip);
        $this->assertNull($dto->ipv4);
        $this->assertSame('2001:db8::42', $dto->ipv6);
    }

    public function test_param_ipv4_used_when_resolver_returns_ipv6(): void
    {
        $factory = $this->makeFactory('2001:db8::1');
        $dto = $factory->build($this->standardParams(['ipv4' => '203.0.113.5', 'ipv6' => null]));

        $this->assertSame('2001:db8::1', $dto->ip);
        $this->assertSame('2001:db8::1', $dto->ipv6);
        $this->assertSame('203.0.113.5', $dto->ipv4);
    }

    public function test_param_ipv6_used_when_resolver_returns_ipv4(): void
    {
        $factory = $this->makeFactory('10.0.0.1');
        $dto = $factory->build($this->standardParams(['ipv4' => null, 'ipv6' => '2001:db8::99']));

        $this->assertSame('10.0.0.1', $dto->ip);
        $this->assertSame('10.0.0.1', $dto->ipv4);
        $this->assertSame('2001:db8::99', $dto->ipv6);
    }

    public function test_invalid_param_ipv4_is_ignored(): void
    {
        $factory = $this->makeFactory('10.0.0.1');
        $dto = $factory->build($this->standardParams(['ipv4' => 'not-an-ip', 'ipv6' => 'also-not-ip']));

        $this->assertSame('10.0.0.1', $dto->ip);
        $this->assertSame('10.0.0.1', $dto->ipv4);
        $this->assertNull($dto->ipv6);
    }

    public function test_compact_defaults_to_false_when_not_set(): void
    {
        $factory = $this->makeFactory();
        $dto = $factory->build($this->standardParams(['compact' => null]));

        $this->assertFalse($dto->compact);
    }

    public function test_numwant_defaults_to_50_when_not_set(): void
    {
        $factory = $this->makeFactory();
        $dto = $factory->build($this->standardParams(['numwant' => null]));

        $this->assertSame(50, $dto->numWant);
    }

    public function test_num_want_alias_works(): void
    {
        $factory = $this->makeFactory();
        $dto = $factory->build($this->standardParams(['numwant' => null, 'num_want' => '75']));

        $this->assertSame(75, $dto->numWant);
    }

    public function test_event_paused_is_preserved(): void
    {
        $factory = $this->makeFactory();
        $dto = $factory->build($this->standardParams(['event' => 'paused']));

        $this->assertSame('paused', $dto->event);
    }

    public function test_event_started_is_preserved(): void
    {
        $factory = $this->makeFactory();
        $dto = $factory->build($this->standardParams(['event' => 'started']));

        $this->assertSame('started', $dto->event);
        $this->assertTrue($dto->isStoppedOrCompleted() === false);
    }

    public function test_event_stopped_is_detected(): void
    {
        $factory = $this->makeFactory();
        $dto = $factory->build($this->standardParams(['event' => 'stopped']));

        $this->assertSame('stopped', $dto->event);
        $this->assertTrue($dto->isStopped());
    }

    public function test_factory_build_does_not_touch_global_state(): void
    {
        // W2-03: build() is pure — calling it multiple times with the same
        // params produces identical DTOs without any side effects.
        $factory = $this->makeFactory('10.0.0.1');
        $params = $this->standardParams();

        $dto1 = $factory->build($params, 'Agent/1.0');
        $dto2 = $factory->build($params, 'Agent/1.0');

        $this->assertSame($dto1->ip, $dto2->ip);
        $this->assertSame($dto1->port, $dto2->port);
        $this->assertSame($dto1->passkey->toString(), $dto2->passkey->toString());
    }
}
