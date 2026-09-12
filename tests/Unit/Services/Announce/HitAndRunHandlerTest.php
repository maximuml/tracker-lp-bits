<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Announce;

use App\Enums\UserClass;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use App\Services\Announce\HitAndRunHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * W5-04: HitAndRunHandler guard-clause coverage.
 *
 * The handler runs inside the announce transaction; the early returns must
 * stay cheap and the snatch-backed path must tolerate a missing row.
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
            $torrent + ['mode' => 'b', 'hr' => 0, 'size' => 1000],
            999999,
            888888,
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
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        Snatch::factory()->forUserAndTorrent($user, $torrent)->create();

        $result = $this->handler->handle(
            100,
            null,
            ['class' => UserClass::USER->value],
            ['mode' => 'b', 'hr' => 0, 'size' => (int) $torrent->size],
            (int) $user->id,
            (int) $torrent->id,
            false,
            '2026-01-01 00:00:00',
            false,
        );

        // hr.mode is 'disabled' in the test settings — the handler must
        // surface the snatch info unchanged instead of creating an H&R row.
        $this->assertIsArray($result);
        $this->assertSame((int) $user->id, (int) $result['userid']);
        $this->assertSame(0, DB::table('hit_and_runs')->where('uid', $user->id)->count());
    }
}
