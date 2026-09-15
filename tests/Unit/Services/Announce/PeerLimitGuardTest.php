<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Announce;

use App\DTOs\AnnounceRequestDto;
use App\Enums\UserClass as UserClassEnum;
use App\Exceptions\TrackerException;
use App\Exceptions\TrackerWarningException;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Announce\PeerLimitGuard;
use App\Support\Settings;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * PeerLimitGuard: wait-time/slot-limit policy and the announce warning path.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class PeerLimitGuardTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        defined('TIMENOW') || define('TIMENOW', time());
    }

    protected function tearDown(): void
    {
        Settings::resetCache();
        parent::tearDown();
    }

    private function makeDto(?string $event = null, bool $compact = false): AnnounceRequestDto
    {
        return new AnnounceRequestDto(
            passkey: Passkey::fromString(str_repeat('a', 32)),
            infoHash: InfoHash::fromBinary(str_repeat("\x00", 20)),
            peerId: PeerId::fromBinary('-qB4500-'.str_repeat("\x01", 12)),
            port: 6881,
            uploaded: 0,
            downloaded: 0,
            left: 100,
            event: $event,
            numWant: 50,
            compact: $compact,
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

    /** @return array<string, mixed> */
    private function makeTorrent(int $ts = 0): array
    {
        return ['ts' => $ts, 'seeders' => 2, 'leechers' => 3, 'times_completed' => 5];
    }

    private function setSetting(string $name, string $value): void
    {
        DB::table('settings')->updateOrInsert(['name' => $name], ['value' => $value, 'autoload' => 1]);
        Settings::resetCache();
    }

    public function test_enforce_skips_vip(): void
    {
        $guard = new PeerLimitGuard($this->makeDto(), $this->makeTorrent());

        $guard->enforceForNewPeer($this->makeUser((int) UserClassEnum::VIP->value, 0, 100 * 1024 ** 3), 1);
        $this->addToAssertionCount(1);
    }

    public function test_enforce_skips_low_download_volume(): void
    {
        $guard = new PeerLimitGuard($this->makeDto(), $this->makeTorrent());

        $guard->enforceForNewPeer($this->makeUser(1, 0, 5 * 1024 ** 3), 1);
        $this->addToAssertionCount(1);
    }

    public function test_enforce_passes_when_systems_disabled(): void
    {
        $this->setSetting('main.waitsystem', 'no');
        $this->setSetting('main.maxdlsystem', 'no');

        $guard = new PeerLimitGuard($this->makeDto(), $this->makeTorrent());
        $guard->enforceForNewPeer($this->makeUser(1, 0, 100 * 1024 ** 3), 1);
        $this->addToAssertionCount(1);
    }

    #[DataProvider('waitHoursProvider')]
    public function test_enforce_warns_when_wait_not_elapsed(int $uploaded, int $downloaded): void
    {
        $this->setSetting('main.waitsystem', 'yes');
        $this->setSetting('main.maxdlsystem', 'no');

        // Torrent added "now" → elapsed 0, always below any wait window.
        $guard = new PeerLimitGuard($this->makeDto(), $this->makeTorrent(TIMENOW));

        $this->expectException(TrackerWarningException::class);
        $guard->enforceForNewPeer($this->makeUser(1, $uploaded, $downloaded), 1);
    }

    /** @return array<string, array{0:int,1:int}> */
    public static function waitHoursProvider(): array
    {
        $g = 1024 ** 3;

        return [
            'ratio below 0.4' => [0, 20 * $g],
            'ratio 0.45' => [9 * $g, 20 * $g],
            'ratio 0.55' => [11 * $g, 20 * $g],
            'ratio 0.75' => [15 * $g, 20 * $g],
        ];
    }

    public function test_enforce_passes_when_wait_elapsed(): void
    {
        $this->setSetting('main.waitsystem', 'yes');
        $this->setSetting('main.maxdlsystem', 'no');

        // Torrent added 30h ago → elapsed exceeds even the 24h window.
        $guard = new PeerLimitGuard($this->makeDto(), $this->makeTorrent(TIMENOW - 30 * 3600));
        $guard->enforceForNewPeer($this->makeUser(1, 0, 20 * 1024 ** 3), 1);
        $this->addToAssertionCount(1);
    }

    public function test_enforce_passes_high_ratio_without_wait(): void
    {
        $this->setSetting('main.waitsystem', 'yes');
        $this->setSetting('main.maxdlsystem', 'no');

        // ratio >= 0.8 → wait = 0, no warn even for a fresh torrent.
        $guard = new PeerLimitGuard($this->makeDto(), $this->makeTorrent(TIMENOW));
        $guard->enforceForNewPeer($this->makeUser(1, 20 * 1024 ** 3, 20 * 1024 ** 3), 1);
        $this->addToAssertionCount(1);
    }

    #[DataProvider('slotLimitProvider')]
    public function test_enforce_throws_when_slot_limit_reached(int $uploaded, int $downloaded, int $expectedMax): void
    {
        $this->setSetting('main.waitsystem', 'no');
        $this->setSetting('main.maxdlsystem', 'yes');

        /** @var User $user */
        $user = User::factory()->create(['class' => 1]);
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->owner($user)->create(['size' => 10000]);

        // Occupy all allowed slots with existing leeching peers.
        for ($i = 0; $i < $expectedMax; $i++) {
            DB::table('peers')->insert([
                'torrent' => $torrent->id,
                'userid' => $user->id,
                'peer_id' => '-qB4500-'.str_pad((string) $i, 12, 'x'),
                'ip' => '10.0.0.'.($i + 10),
                'port' => 6881,
                'uploaded' => 0,
                'downloaded' => 0,
                'to_go' => 500,
                'seeder' => 0,
                'started' => date('Y-m-d H:i:s'),
                'last_action' => date('Y-m-d H:i:s'),
                'agent' => 'qBittorrent/4.5.0',
                'passkey' => $user->passkey,
            ]);
        }

        $guard = new PeerLimitGuard($this->makeDto(), $this->makeTorrent());

        $this->expectException(TrackerException::class);
        $this->expectExceptionMessage('slot limit');
        $guard->enforceForNewPeer($this->makeUser(1, $uploaded, $downloaded), $user->id);
    }

    /** @return array<string, array{0:int,1:int,2:int}> */
    public static function slotLimitProvider(): array
    {
        $g = 1024 ** 3;

        return [
            'ratio below 0.5 allows 1 slot' => [0, 20 * $g, 1],
            'ratio 0.55 allows 2 slots' => [11 * $g, 20 * $g, 2],
            'ratio 0.9 allows 4 slots' => [18 * $g, 20 * $g, 4],
        ];
    }

    public function test_enforce_passes_with_free_slot(): void
    {
        $this->setSetting('main.waitsystem', 'no');
        $this->setSetting('main.maxdlsystem', 'yes');

        /** @var User $user */
        $user = User::factory()->create(['class' => 1]);
        $guard = new PeerLimitGuard($this->makeDto(), $this->makeTorrent());

        // ratio < 0.5 → max 1 leech, but no existing leeching peers.
        $guard->enforceForNewPeer($this->makeUser(1, 0, 20 * 1024 ** 3), $user->id);
        $this->addToAssertionCount(1);
    }

    public function test_enforce_passes_high_ratio_no_slot_cap(): void
    {
        $this->setSetting('main.waitsystem', 'no');
        $this->setSetting('main.maxdlsystem', 'yes');

        /** @var User $user */
        $user = User::factory()->create(['class' => 1]);
        // ratio 1.0 → max = 0 → no cap regardless of peer count.
        $guard = new PeerLimitGuard($this->makeDto(), $this->makeTorrent());
        $guard->enforceForNewPeer($this->makeUser(1, 25 * 1024 ** 3, 20 * 1024 ** 3), $user->id);
        $this->addToAssertionCount(1);
    }

    public function test_warn_throws_warning_for_regular_announce(): void
    {
        $guard = new PeerLimitGuard($this->makeDto(), $this->makeTorrent());

        try {
            $guard->warn('too fast', 300);
            $this->fail('expected TrackerWarningException');
        } catch (TrackerWarningException $e) {
            $response = $e->getResponse();
            $this->assertSame('too fast', $e->getMessage());
            $this->assertSame(300, $response['interval']);
            $this->assertSame(300, $response['min interval']);
            $this->assertSame(2, $response['complete']);
            $this->assertSame(3, $response['incomplete']);
            $this->assertSame(5, $response['downloaded']);
            $this->assertSame([], $response['peers']);
            $this->assertArrayNotHasKey('peers6', $response);
        }
    }

    public function test_warn_compact_response_uses_empty_peer_lists(): void
    {
        $guard = new PeerLimitGuard($this->makeDto(null, compact: true), $this->makeTorrent());

        try {
            $guard->warn('slow down');
            $this->fail('expected TrackerWarningException');
        } catch (TrackerWarningException $e) {
            $response = $e->getResponse();
            $this->assertSame(7200, $response['interval']);
            $this->assertSame('', $response['peers']);
            $this->assertSame('', $response['peers6']);
        }
    }

    public function test_warn_escalates_to_failure_on_completed_event(): void
    {
        $guard = new PeerLimitGuard($this->makeDto('completed'), $this->makeTorrent());

        $this->expectException(TrackerException::class);
        $guard->warn('not allowed');
    }

    public function test_warn_escalates_to_failure_on_stopped_event(): void
    {
        $guard = new PeerLimitGuard($this->makeDto('stopped'), $this->makeTorrent());

        $this->expectException(TrackerException::class);
        $guard->warn('not allowed');
    }

    public function test_warn_stays_warning_on_started_event(): void
    {
        $guard = new PeerLimitGuard($this->makeDto('started'), $this->makeTorrent());

        $this->expectException(TrackerWarningException::class);
        $guard->warn('slow down');
    }
}
