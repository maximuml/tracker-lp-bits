<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Announce;

use App\DTOs\AnnounceRequestDto;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Services\Announce\RateLimiter;
use App\Support\RedisGuard;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W5-04: RateLimiter fail-open and dedup semantics.
 *
 * The limiter sits on the announce hot path; every Redis call must go
 * through RedisGuard so a Redis outage degrades to DB-only instead of
 * stalling each request on connect timeouts.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class RateLimiterTest extends TestCase
{
    private RateLimiter $limiter;

    private string $hashByte = "\xAB";

    protected function setUp(): void
    {
        parent::setUp();
        RedisGuard::reset();
        $this->limiter = new RateLimiter;
    }

    protected function tearDown(): void
    {
        RedisGuard::reset();
        parent::tearDown();
    }

    private function infoHash(): string
    {
        return str_repeat($this->hashByte, 20);
    }

    private function dto(string $passkey, ?string $event = null): AnnounceRequestDto
    {
        return new AnnounceRequestDto(
            passkey: Passkey::fromString($passkey),
            infoHash: InfoHash::fromBinary($this->infoHash()),
            peerId: PeerId::fromBinary('-qB5000-'.str_repeat("\x01", 12)),
            port: 6881,
            uploaded: 0,
            downloaded: 0,
            left: 100,
            event: $event,
            numWant: 50,
            compact: true,
            ipv4: '10.9.0.1',
            ipv6: null,
            ip: '10.9.0.1',
            userAgent: 'PHPUnit/Test',
        );
    }

    public function test_first_announce_is_not_reannounce(): void
    {
        $this->hashByte = "\xA0";
        $result = $this->limiter->check($this->dto(bin2hex(random_bytes(16))));

        $this->assertFalse($result->isReAnnounce);
    }

    public function test_second_announce_within_window_is_reannounce(): void
    {
        $this->hashByte = "\xA1";
        $passkey = bin2hex(random_bytes(16));
        $dto = $this->dto($passkey);

        $this->assertFalse($this->limiter->check($dto)->isReAnnounce);
        $this->assertTrue($this->limiter->check($dto)->isReAnnounce);
    }

    public function test_invalid_passkey_flag_warns_regular_announce(): void
    {
        $this->hashByte = "\xA2";
        $passkey = bin2hex(random_bytes(16));
        Redis::connection()->client()->set("passkey_invalid:{$passkey}", time(), ['ex' => 60]);

        $this->expectException(TrackerWarningException::class);
        $this->limiter->check($this->dto($passkey));
    }

    public function test_invalid_passkey_flag_fails_stopped_announce(): void
    {
        $this->hashByte = "\xA3";
        $passkey = bin2hex(random_bytes(16));
        Redis::connection()->client()->set("passkey_invalid:{$passkey}", time(), ['ex' => 60]);

        $this->expectException(TrackerException::class);
        $this->limiter->check($this->dto($passkey, 'stopped'));
    }

    public function test_torrent_not_exists_flag_throws_failure(): void
    {
        $this->hashByte = "\xA4";
        Redis::connection()->client()->set('torrent_not_exists:'.$this->infoHash(), time(), ['ex' => 60]);

        $this->expectException(TrackerException::class);
        $this->limiter->check($this->dto(bin2hex(random_bytes(16))));
    }

    public function test_frequency_gate_warns_within_window(): void
    {
        $this->hashByte = "\xA5";
        $passkey = bin2hex(random_bytes(16));
        $fingerprint = InfoHash::fromBinary($this->infoHash())->fingerprint();
        Redis::connection()->client()->set("reAnnounceCheckByInfoHash:{$passkey}:{$fingerprint}", time(), ['ex' => 60]);

        $this->expectException(TrackerWarningException::class);
        $this->limiter->check($this->dto($passkey));
    }

    public function test_stopped_event_bypasses_frequency_gate(): void
    {
        $this->hashByte = "\xA6";
        $passkey = bin2hex(random_bytes(16));
        $fingerprint = InfoHash::fromBinary($this->infoHash())->fingerprint();
        Redis::connection()->client()->set("reAnnounceCheckByInfoHash:{$passkey}:{$fingerprint}", time(), ['ex' => 60]);

        $result = $this->limiter->check($this->dto($passkey, 'stopped'));

        $this->assertFalse($result->isReAnnounce);
    }

    public function test_redis_down_fails_open_without_touching_redis(): void
    {
        RedisGuard::markDown();

        $result = $this->limiter->check($this->dto(bin2hex(random_bytes(16))));

        $this->assertFalse($result->isReAnnounce);
    }
}
