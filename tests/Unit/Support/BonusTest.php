<?php

namespace Tests\Unit\Support;

use App\Support\Bonus;
use PHPUnit\Framework\TestCase;

/**
 * Pins down the seeding-bonus math drained out of
 * `calculate_seed_bonus()` into App\Support\Bonus (Phase 5).
 *
 * The formula depends on time() (via weeks-alive), so expected values
 * are recomputed in-test using the same constants rather than hardcoded.
 */
final class BonusTest extends TestCase
{
    /** A representative `bonus` setting group. */
    private function settings(array $overrides = []): array
    {
        return array_merge([
            'donortimes' => 2,
            'perseeding' => 0.5,
            'maxseeding' => 3,
            'tzero' => 24,
            'nzero' => 5,
            'bzero' => 2.5,
            'l' => 100,
            'min_size' => 0,
        ], $overrides);
    }

    /**
     * Reference implementation of a single torrent's contribution,
     * mirroring the legacy inline math, for cross-checking.
     */
    private function expectedTemp(array $t, array $s, float $gbFactor = 1.0): float
    {
        $sqrtof2 = sqrt(2);
        $logofpointone = log(0.1);
        $valueone = $logofpointone / $s['tzero'];
        $valuethree = $logofpointone / ($s['nzero'] - 1);
        $sectoweek = 7 * 24 * 60 * 60;
        $weeks_alive = (time() - strtotime($t['added'])) / $sectoweek;
        $gb_size = ($t['size'] / 1073741824) * $gbFactor;

        return (1 - exp($valueone * $weeks_alive)) * $gb_size * (1 + $sqrtof2 * exp($valuethree * ($t['seeders'] - 1)));
    }

    private function finalBonus(float $A, int $count, array $s): float
    {
        $pi = 3.141592653589793;
        $valuetwo = $s['bzero'] * (2 / $pi);

        return $valuetwo * atan($A / $s['l']) + ($s['perseeding'] * $count);
    }

    public function test_empty_torrent_list_yields_zero_bonus(): void
    {
        $r = Bonus::aggregateSeedBonus([], $this->settings(), [], null, null, null, 0.0, 0);

        $this->assertSame(0, $r['count']);
        $this->assertSame(0, $r['torrent_peer_count']);
        $this->assertEqualsWithDelta(0.0, $r['seed_bonus'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $r['seed_points'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $r['A'], 1e-9);
        $this->assertSame('0', (string) $r['size']);
        $this->assertSame([], $r['ip_arr']);
        $this->assertSame('', $r['last_action']);
        $this->assertSame(0, $r['official_torrent_peer_count']);
        $this->assertSame(2, $r['donor_times']);
        $this->assertSame(0.0, $r['medal_additional_factor']);
    }

    public function test_single_torrent_matches_reference_formula(): void
    {
        $s = $this->settings();
        $t = [
            'id' => 7,
            'added' => date('Y-m-d H:i:s', time() - 14 * 24 * 60 * 60), // 2 weeks
            'size' => 5 * 1073741824, // 5 GiB
            'seeders' => 3,
            'last_action' => '2026-05-01 10:00:00',
            'ip' => '10.0.0.1',
        ];

        $r = Bonus::aggregateSeedBonus([$t], $s, [], null, null, null, 0.0, 0);

        $expectedA = $this->expectedTemp($t, $s);
        $this->assertSame(1, $r['count']);
        $this->assertEqualsWithDelta($expectedA, $r['A'], 1e-6);
        $this->assertEqualsWithDelta($this->finalBonus($expectedA, 1, $s), $r['seed_bonus'], 1e-6);
        $this->assertSame(['10.0.0.1'], $r['ip_arr']);
        $this->assertSame('2026-05-01 10:00:00', $r['last_action']);
    }

    public function test_count_is_capped_at_maxseeding(): void
    {
        $s = $this->settings(['maxseeding' => 2, 'perseeding' => 1.0]);
        $rows = [];
        for ($i = 1; $i <= 5; $i++) {
            $rows[] = [
                'id' => $i,
                'added' => date('Y-m-d H:i:s', time() - 30 * 24 * 60 * 60),
                'size' => 1073741824,
                'seeders' => 1,
                'last_action' => '',
                'ip' => "10.0.0.$i",
            ];
        }

        $r = Bonus::aggregateSeedBonus($rows, $s, [], null, null, null, 0.0, 0);

        // 5 torrents counted, but the count used for per-seeding bonus is capped at 2.
        $this->assertSame(2, $r['count']);
        $this->assertSame(5, $r['torrent_peer_count']);
        $this->assertCount(5, $r['ip_arr']);
    }

    public function test_official_tag_feeds_official_aggregates(): void
    {
        $s = $this->settings();
        $rows = [
            ['id' => 1, 'added' => date('Y-m-d H:i:s', time() - 7 * 86400), 'size' => 2 * 1073741824, 'seeders' => 2, 'last_action' => '', 'ip' => ''],
            ['id' => 2, 'added' => date('Y-m-d H:i:s', time() - 7 * 86400), 'size' => 3 * 1073741824, 'seeders' => 2, 'last_action' => '', 'ip' => ''],
        ];
        $tagGrouped = [2 => [99 => 1]]; // torrent 2 carries the official tag

        $r = Bonus::aggregateSeedBonus($rows, $s, $tagGrouped, 99, null, null, 0.0, 0);

        $this->assertSame(1, $r['official_torrent_peer_count']);
        $this->assertSame('3221225472', (string) $r['official_size']); // 3 GiB
        $this->assertGreaterThan(0.0, $r['official_a']);
        $this->assertLessThan($r['A'], $r['official_a']);
    }

    public function test_zero_bonus_tag_scales_contribution_down(): void
    {
        $s = $this->settings();
        $row = ['id' => 1, 'added' => date('Y-m-d H:i:s', time() - 7 * 86400), 'size' => 4 * 1073741824, 'seeders' => 1, 'last_action' => '', 'ip' => ''];

        $full = Bonus::aggregateSeedBonus([$row], $s, [], null, null, null, 0.0, 0);
        $scaled = Bonus::aggregateSeedBonus([$row], $s, [1 => [42 => 1]], null, 42, 0.25, 0.0, 0);

        $this->assertEqualsWithDelta($full['A'] * 0.25, $scaled['A'], 1e-6);
    }

    public function test_last_action_takes_the_maximum(): void
    {
        $s = $this->settings();
        $rows = [
            ['id' => 1, 'added' => date('Y-m-d H:i:s', time() - 86400), 'size' => 1073741824, 'seeders' => 1, 'last_action' => '2026-01-01 00:00:00', 'ip' => '1.1.1.1'],
            ['id' => 2, 'added' => date('Y-m-d H:i:s', time() - 86400), 'size' => 1073741824, 'seeders' => 1, 'last_action' => '2026-05-20 12:00:00', 'ip' => '1.1.1.1'],
        ];

        $r = Bonus::aggregateSeedBonus($rows, $s, [], null, null, null, 0.0, 0);

        $this->assertSame('2026-05-20 12:00:00', $r['last_action']);
        $this->assertSame(['1.1.1.1'], $r['ip_arr']); // deduplicated
    }
}
