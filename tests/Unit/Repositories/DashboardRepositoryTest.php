<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\UserClass;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\DashboardRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for DashboardRepository.
 *
 * Covers getSystemInfo(), getStatData(), statUserClass(), statUsers(),
 * statTorrents(), latestUser(), latestTorrent(), statTracker(),
 * uploaderActivity(), categoryActivity(), peerAgents(), and donorSummary().
 */
final class DashboardRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private DashboardRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('peers')->delete();
        DB::table('torrents')->delete();
        DB::table('users')->delete();
        DB::table('categories')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->repository = new DashboardRepository;
    }

    public function test_get_system_info_returns_expected_keys(): void
    {
        $result = $this->repository->getSystemInfo();

        $this->assertArrayHasKey('nexus_version', $result);
        $this->assertArrayHasKey('php_version', $result);
        $this->assertArrayHasKey('mysql_version', $result);
        $this->assertArrayHasKey('redis_version', $result);
        $this->assertArrayHasKey('laravel_version', $result);
        $this->assertArrayHasKey('load_average', $result);
    }

    public function test_get_system_info_values_are_strings(): void
    {
        $result = $this->repository->getSystemInfo();

        $this->assertIsString($result['nexus_version']['value']);
        $this->assertIsString($result['php_version']['value']);
    }

    public function test_get_stat_data_returns_four_sections(): void
    {
        $result = $this->repository->getStatData();

        $this->assertArrayHasKey('user_class', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('torrent', $result);
        $this->assertArrayHasKey('system_info', $result);
    }

    public function test_stat_user_class_returns_counts_per_class(): void
    {
        User::factory()->class(UserClass::USER->value)->create();
        User::factory()->class(UserClass::USER->value)->create();
        User::factory()->class(UserClass::POWER_USER->value)->create();

        $result = $this->repository->statUserClass();

        $this->assertArrayHasKey(UserClass::USER->value, $result);
        $this->assertArrayHasKey(UserClass::POWER_USER->value, $result);
        $this->assertSame('2', $result[UserClass::USER->value]['value']);
        $this->assertSame('1', $result[UserClass::POWER_USER->value]['value']);
    }

    public function test_stat_users_returns_expected_keys(): void
    {
        User::factory()->create();

        $result = $this->repository->statUsers();

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('unconfirmed', $result);
        $this->assertArrayHasKey('vip', $result);
        $this->assertArrayHasKey('donated', $result);
        $this->assertArrayHasKey('warned', $result);
        $this->assertArrayHasKey('disabled', $result);
    }

    public function test_stat_users_counts_total(): void
    {
        User::factory()->create();
        User::factory()->create();

        $result = $this->repository->statUsers();

        $this->assertStringContainsString('2', $result['total']['value']);
    }

    public function test_stat_users_counts_disabled(): void
    {
        User::factory()->create();
        User::factory()->disabled()->create();

        $result = $this->repository->statUsers();

        $this->assertSame('1', $result['disabled']['value']);
    }

    public function test_stat_torrents_returns_expected_keys(): void
    {
        $result = $this->repository->statTorrents();

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('dead', $result);
        $this->assertArrayHasKey('seeders', $result);
        $this->assertArrayHasKey('leechers', $result);
        $this->assertArrayHasKey('seeders_leechers', $result);
        $this->assertArrayHasKey('total_torrent_size', $result);
        $this->assertArrayHasKey('total_uploaded', $result);
        $this->assertArrayHasKey('total_downloaded', $result);
    }

    public function test_stat_torrents_counts_total(): void
    {
        Torrent::factory()->create();
        Torrent::factory()->create();

        $result = $this->repository->statTorrents();

        $this->assertSame('2', $result['total']['value']);
    }

    public function test_stat_torrents_counts_dead(): void
    {
        Torrent::factory()->create();
        Torrent::factory()->invisible()->create();

        $result = $this->repository->statTorrents();

        $this->assertSame('1', $result['dead']['value']);
    }

    public function test_stat_torrents_counts_seeders_and_leechers(): void
    {
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        Peer::factory()->torrent($torrent)->seeder()->create();
        Peer::factory()->torrent($torrent)->leecher()->create();

        $result = $this->repository->statTorrents();

        $this->assertSame('1', $result['seeders']['value']);
        $this->assertSame('1', $result['leechers']['value']);
        $this->assertSame('2', $result['seeders_leechers']['value']);
    }

    public function test_latest_user_returns_recent_users(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();

        $result = $this->repository->latestUser();

        $this->assertCount(2, $result);
        $this->assertSame($user2->id, $result->first()->id);
    }

    public function test_latest_user_limits_to_ten(): void
    {
        for ($i = 0; $i < 15; $i++) {
            User::factory()->create();
        }

        $result = $this->repository->latestUser();

        $this->assertCount(10, $result);
    }

    public function test_latest_torrent_returns_recent_torrents(): void
    {
        /** @var Torrent $torrent1 */
        $torrent1 = Torrent::factory()->create();
        /** @var Torrent $torrent2 */
        $torrent2 = Torrent::factory()->create();

        $result = $this->repository->latestTorrent();

        $this->assertCount(2, $result);
        $this->assertSame($torrent2->id, $result->first()->id);
    }

    public function test_latest_torrent_limits_to_five(): void
    {
        for ($i = 0; $i < 10; $i++) {
            Torrent::factory()->create();
        }

        $result = $this->repository->latestTorrent();

        $this->assertCount(5, $result);
    }

    public function test_stat_tracker_returns_expected_entries(): void
    {
        User::factory()->create();
        Torrent::factory()->create();

        $result = $this->repository->statTracker();

        $names = array_column($result, 'name');
        $this->assertContains('total_torrents', $names);
        $this->assertContains('total_peers', $names);
        $this->assertContains('total_users', $names);
        $this->assertContains('seeders', $names);
        $this->assertContains('leechers', $names);
    }

    public function test_stat_tracker_counts_correctly(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        User::factory()->create();
        Torrent::factory()->owner($user)->create();

        $result = $this->repository->statTracker();

        $totalUsers = $this->findStatValue($result, 'total_users');
        $totalTorrents = $this->findStatValue($result, 'total_torrents');

        $this->assertSame('2', $totalUsers);
        $this->assertSame('1', $totalTorrents);
    }

    public function test_uploader_activity_returns_empty_when_no_uploaders(): void
    {
        $result = $this->repository->uploaderActivity();

        $this->assertSame([], $result);
    }

    public function test_uploader_activity_returns_uploaders_with_torrents(): void
    {
        /** @var User $uploader */
        $uploader = User::factory()->class(UserClass::UPLOADER->value)->create();
        Torrent::factory()->owner($uploader)->create();

        $result = $this->repository->uploaderActivity();

        $this->assertCount(1, $result);
        $this->assertSame($uploader->username, $result[0]['text']);
    }

    public function test_category_activity_returns_empty_when_no_categories(): void
    {
        $result = $this->repository->categoryActivity();

        $this->assertSame([], $result);
    }

    public function test_category_activity_returns_categories_with_torrents(): void
    {
        $catId = (int) DB::table('categories')->insertGetId([
            'name' => 'Movies',
            'mode' => 1,
            'class_name' => 'movies',
        ]);
        Torrent::factory()->category($catId)->create();

        $result = $this->repository->categoryActivity();

        $this->assertCount(1, $result);
        $this->assertSame('Movies', $result[0]['text']);
    }

    public function test_peer_agents_returns_empty_when_no_peers(): void
    {
        $result = $this->repository->peerAgents();

        $this->assertSame([], $result);
    }

    public function test_peer_agents_groups_by_agent(): void
    {
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();
        Peer::factory()->torrent($torrent)->create(['agent' => 'qBittorrent/4.5.0']);
        Peer::factory()->torrent($torrent)->create(['agent' => 'qBittorrent/4.5.0']);
        Peer::factory()->torrent($torrent)->create(['agent' => 'Transmission/3.0']);

        $result = $this->repository->peerAgents();

        $this->assertCount(2, $result);
        $qb = $this->findStatByText($result, 'qBittorrent/4.5.0');
        $this->assertSame('2', $qb);
    }

    public function test_donor_summary_returns_empty_when_no_donors(): void
    {
        $result = $this->repository->donorSummary();

        $this->assertSame([], $result);
    }

    public function test_donor_summary_returns_donors_ordered_by_amount(): void
    {
        User::factory()->create(['donor' => true, 'donated' => 100.0, 'donated_cny' => 500.0, 'donoruntil' => '2026-12-31']);
        User::factory()->create(['donor' => true, 'donated' => 500.0, 'donated_cny' => 100.0, 'donoruntil' => null]);

        $result = $this->repository->donorSummary();

        $this->assertCount(2, $result);
        // Ordered by donated desc
        $this->assertStringContainsString('500.00', $result[0]['value']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $stats
     */
    private function findStatValue(array $stats, string $name): string
    {
        foreach ($stats as $stat) {
            if ($stat['name'] === $name) {
                return (string) $stat['value'];
            }
        }

        return '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $stats
     */
    private function findStatByText(array $stats, string $text): string
    {
        foreach ($stats as $stat) {
            if ($stat['text'] === $text) {
                return (string) $stat['value'];
            }
        }

        return '';
    }
}
