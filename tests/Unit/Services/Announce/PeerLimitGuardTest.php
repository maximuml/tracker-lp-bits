<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Announce;

use App\DTOs\AnnounceRequestDto;
use App\Enums\UserClass as UserClassEnum;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Services\Announce\PeerLimitGuard;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * PeerLimitGuard: wait-time/slot-limit policy and the announce warning path.
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class PeerLimitGuardTest extends TestCase
{
    private function makeDto(?string $event = null): AnnounceRequestDto
    {
        return new AnnounceRequestDto(
            passkey: Passkey::fromString('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
            infoHash: InfoHash::fromBinary(str_repeat("\x00", 20)),
            peerId: PeerId::fromBinary('-qB4500-'."\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c"),
            port: 6881,
            uploaded: 0,
            downloaded: 0,
            left: 100,
            event: $event,
            numWant: 50,
            compact: false,
            ipv4: '127.0.0.1',
            ipv6: null,
            ip: '127.0.0.1',
            userAgent: 'Test/1.0',
        );
    }

    /** @return array<string, mixed> */
    private function makeUser(int $class = 1, int $uploaded = 0, int $downloaded = 0): array
    {
        return [
            'id' => 1,
            'class' => $class,
            'uploaded' => $uploaded,
            'downloaded' => $downloaded,
        ];
    }

    public function test_enforce_skips_vip(): void
    {
        $guard = new PeerLimitGuard($this->makeDto(), ['ts' => 0, 'seeders' => 0, 'leechers' => 0, 'times_completed' => 0]);

        // VIP users bypass the wait/slot checks entirely — no exception.
        $guard->enforceForNewPeer($this->makeUser((int) UserClassEnum::VIP->value, 0, 100 * 1024 ** 3), 1);
        $this->addToAssertionCount(1);
    }

    public function test_enforce_skips_low_download_volume(): void
    {
        $guard = new PeerLimitGuard($this->makeDto(), ['ts' => 0, 'seeders' => 0, 'leechers' => 0, 'times_completed' => 0]);

        // <=10GB downloaded never triggers the checks.
        $guard->enforceForNewPeer($this->makeUser(1, 0, 5 * 1024 ** 3), 1);
        $this->addToAssertionCount(1);
    }

    public function test_warn_throws_warning_for_regular_announce(): void
    {
        $guard = new PeerLimitGuard($this->makeDto(), ['ts' => 0, 'seeders' => 2, 'leechers' => 3, 'times_completed' => 5]);

        $this->expectException(TrackerWarningException::class);
        $guard->warn('too fast', 300);
    }

    public function test_warn_escalates_to_failure_on_completed_event(): void
    {
        $guard = new PeerLimitGuard($this->makeDto('completed'), ['ts' => 0, 'seeders' => 0, 'leechers' => 0, 'times_completed' => 0]);

        $this->expectException(TrackerException::class);
        $guard->warn('not allowed');
    }

    public function test_warn_escalates_to_failure_on_stopped_event(): void
    {
        $guard = new PeerLimitGuard($this->makeDto('stopped'), ['ts' => 0, 'seeders' => 0, 'leechers' => 0, 'times_completed' => 0]);

        $this->expectException(TrackerException::class);
        $guard->warn('not allowed');
    }
}
