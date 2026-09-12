<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Announce;

use App\Enums\TorrentHr;
use App\Enums\UserClass;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Announce\HitAndRunHandler;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W5-04: HitAndRunHandler guard-clause and mode coverage.
 *
 * The handler runs inside the announce transaction; the early returns must
 * stay cheap, the snatch-backed path must tolerate a missing row, and an
 * enabled H&R mode must actually create the hit_and_runs row.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class HitAndRunHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private HitAndRunHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new HitAndRunHandler;
    }

    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $torrent
     * @return array<string, mixed>|null
     */
    private function handle(array $user = [], array $torrent = [], int $left = 100, ?string $event = null, bool $isDonor = false): ?array
    {
        return $this->handler->handle(
            $left,
            $event,
            $user + ['class' => UserClass::USER->value],
            $torrent + ['mode' => 'b', 'hr' => TorrentHr::NO->value, 'size' => 1000],
            999999,
            888888,
            $isDonor,
            '2026-01-01 00:00:00',
            false,
        );
    }

    /**
     * A real user + torrent + snatch row so the handler reaches the
     * mode/insert paths instead of bailing on the snatch lookup.
     *
     * @return array{0: User, 1: Torrent}
     */
    private function snatchedPair(int $downloaded = 0): array
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create(['size' => 1000]);
        Snatch::factory()->forUserAndTorrent($user, $torrent)->create([
            'downloaded' => $downloaded,
        ]);

        return [$user, $torrent];
    }

    /**
     * @param  array<string, mixed>  $torrentRow
     * @return array<string, mixed>|null
     */
    private function handlePair(User $user, Torrent $torrent, array $torrentRow = [], int $left = 100, ?string $event = null, int $class = UserClass::USER->value, bool $isDonor = false): ?array
    {
        return $this->handler->handle(
            $left,
            $event,
            ['class' => $class],
            $torrentRow + ['mode' => 'b', 'hr' => TorrentHr::NO->value, 'size' => (int) $torrent->size],
            (int) $user->id,
            (int) $torrent->id,
            $isDonor,
            '2026-01-01 00:00:00',
            false,
        );
    }

    public function test_zero_left_without_completed_event_returns_null(): void
    {
        $this->assertNull($this->handle(left: 0, event: null));
    }

    public function test_vip_user_returns_null(): void
    {
        $this->assertNull($this->handle(user: ['class' => UserClass::VIP->value]));
    }

    public function test_donor_returns_null(): void
    {
        $this->assertNull($this->handle(isDonor: true));
    }

    public function test_empty_torrent_mode_returns_null(): void
    {
        $this->assertNull($this->handle(torrent: ['mode' => '']));
    }

    public function test_missing_snatch_row_returns_null(): void
    {
        // No snatched row for these ids — the handler must not fail.
        $this->assertNull($this->handle());
    }

    public function test_disabled_hr_mode_returns_snatch_info(): void
    {
        [$user, $torrent] = $this->snatchedPair();

        $result = $this->handlePair($user, $torrent);

        // hr.mode is 'disabled' in the test settings — the handler must
        // surface the snatch info unchanged instead of creating an H&R row.
        $this->assertIsArray($result);
        $this->assertSame((int) $user->id, (int) $result['userid']);
        $this->assertSame(0, DB::table('hit_and_runs')->where('uid', $user->id)->count());
    }

    public function test_completed_event_with_zero_left_still_processes(): void
    {
        [$user, $torrent] = $this->snatchedPair();

        // left=0 alone must not skip processing when the event is
        // 'completed' — the guard is `left <= 0 && event !== 'completed'`.
        $result = $this->handlePair($user, $torrent, left: 0, event: 'completed');

        $this->assertIsArray($result);
        $this->assertSame((int) $user->id, (int) $result['userid']);
    }

    public function test_class_just_below_vip_still_processes(): void
    {
        [$user, $torrent] = $this->snatchedPair();

        // ULTIMATE_USER (9) is just below VIP (10) — boundary for the
        // class >= VIP early return.
        $result = $this->handlePair($user, $torrent, class: UserClass::ULTIMATE_USER->value);

        $this->assertIsArray($result);
        $this->assertSame((int) $user->id, (int) $result['userid']);
    }

    public function test_vip_with_snatch_row_returns_null(): void
    {
        [$user, $torrent] = $this->snatchedPair();

        $this->assertNull($this->handlePair($user, $torrent, class: UserClass::VIP->value));
    }

    public function test_donor_with_snatch_row_returns_null(): void
    {
        [$user, $torrent] = $this->snatchedPair();

        $this->assertNull($this->handlePair($user, $torrent, isDonor: true));
    }

    public function test_empty_mode_with_snatch_row_returns_null(): void
    {
        [$user, $torrent] = $this->snatchedPair();

        $this->assertNull($this->handlePair($user, $torrent, torrentRow: ['mode' => '']));
    }

    public function test_zero_left_with_snatch_row_returns_null(): void
    {
        [$user, $torrent] = $this->snatchedPair();

        $this->assertNull($this->handlePair($user, $torrent, left: 0));
    }

    public function test_global_hr_mode_creates_hit_and_run(): void
    {
        Settings::saveBatch('hr', ['mode' => 'global']);
        [$user, $torrent] = $this->snatchedPair(downloaded: 2000);

        $result = $this->handlePair($user, $torrent);

        $this->assertIsArray($result);
        // include_rate=1 → required = size; downloaded (2000) >= 1000
        // → the H&R record must actually be inserted.
        $this->assertSame(1, DB::table('hit_and_runs')->where('uid', $user->id)->where('torrent_id', $torrent->id)->count());
    }

    public function test_manual_mode_without_hr_flag_returns_snatch_info(): void
    {
        Settings::saveBatch('hr', ['mode' => 'manual']);
        [$user, $torrent] = $this->snatchedPair(downloaded: 2000);

        // MANUAL mode requires torrent.hr = YES — with NO the handler must
        // return the snatch info without creating an H&R row.
        $result = $this->handlePair($user, $torrent, torrentRow: ['hr' => TorrentHr::NO->value]);

        $this->assertIsArray($result);
        $this->assertSame(0, DB::table('hit_and_runs')->where('uid', $user->id)->count());
    }

    public function test_manual_mode_with_hr_flag_creates_hit_and_run(): void
    {
        Settings::saveBatch('hr', ['mode' => 'manual']);
        [$user, $torrent] = $this->snatchedPair(downloaded: 2000);

        $result = $this->handlePair($user, $torrent, torrentRow: ['hr' => TorrentHr::YES->value]);

        $this->assertIsArray($result);
        $this->assertSame(1, DB::table('hit_and_runs')->where('uid', $user->id)->where('torrent_id', $torrent->id)->count());
    }
}
