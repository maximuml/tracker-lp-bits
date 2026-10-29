<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Repositories\BonusCalculationRepository;
use App\Services\BonusCalculator;
use App\Services\SeedBonusService;
use App\ViewModels\Bonus\BonusTableViewFactory;
use Mockery;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Integration tests for SeedBonusService.
 *
 * Focused on the legacy default-uid semantics: a user array without an
 * `id` key must resolve to uid 0 for both the torrent-row lookup and the
 * harem-addition lookup (calculate_seed_bonus/build_bonus_table parity).
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class SeedBonusServiceTest extends TestCase
{
    private function service(BonusCalculationRepository $repo): SeedBonusService
    {
        return new SeedBonusService(
            $repo,
            new BonusCalculator,
            $this->app->make(BonusTableViewFactory::class),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function bonusResult(): array
    {
        return [
            'seed_bonus' => 1.5,
            'A' => 2.0,
            'size' => 1024.0,
            'torrent_peer_count' => 3,
            'official_bonus' => 0.5,
            'official_a' => 1.0,
            'official_size' => 512.0,
            'official_torrent_peer_count' => 1,
        ];
    }

    public function test_build_bonus_table_resolves_missing_user_id_to_zero_for_harem(): void
    {
        $repo = Mockery::mock(BonusCalculationRepository::class);
        $repo->shouldReceive('getHaremAddition')->with(0)->once()->andReturn(2.5);

        $result = $this->service($repo)->buildBonusTableForUser(
            ['username' => 'testuser', 'donor' => 'no'],
            $this->bonusResult(),
        );

        $this->assertIsString($result['table']);
        $this->assertArrayHasKey('has_harem_addition', $result);
    }

    public function test_build_bonus_table_resolves_missing_user_id_to_zero_for_calculation(): void
    {
        $repo = Mockery::mock(BonusCalculationRepository::class);
        $repo->shouldReceive('getTorrentRowsForBonusCalculation')
            ->with(0, Mockery::any(), Mockery::any())
            ->once()
            ->andReturn(['sql' => 'SELECT ...', 'torrentResult' => []]);
        $repo->shouldReceive('getHaremAddition')->with(0)->once()->andReturn(0);

        $result = $this->service($repo)->buildBonusTableForUser(['username' => 'testuser', 'donor' => 'no']);

        $this->assertIsString($result['table']);
    }

    public function test_calculate_for_user_groups_tags_when_torrents_found(): void
    {
        $repo = Mockery::mock(BonusCalculationRepository::class);
        $repo->shouldReceive('getTorrentRowsForBonusCalculation')
            ->with(7, [10], Mockery::any())
            ->once()
            ->andReturn([
                'sql' => 'SELECT ...',
                'torrentResult' => [[
                    'id' => 10,
                    'size' => '2147483648',
                    'added' => '2020-01-01 00:00:00',
                    'seeders' => 5,
                    'last_action' => '2026-01-01 00:00:00',
                    'ip' => '1.2.3.4',
                ]],
            ]);
        $repo->shouldReceive('getTagGrouped')->with([10])->once()->andReturn([]);

        $result = $this->service($repo)->calculateForUser(7, [10]);

        $this->assertArrayHasKey('seed_bonus', $result);
        $this->assertSame(1, $result['torrent_peer_count']);
    }

    public function test_calculate_for_user_skips_tag_grouping_when_no_torrents(): void
    {
        $repo = Mockery::mock(BonusCalculationRepository::class);
        $repo->shouldReceive('getTorrentRowsForBonusCalculation')
            ->once()
            ->andReturn(['sql' => 'SELECT ...', 'torrentResult' => []]);
        $repo->shouldNotReceive('getTagGrouped');

        $result = $this->service($repo)->calculateForUser(7);

        $this->assertSame(0, $result['torrent_peer_count']);
    }
}
