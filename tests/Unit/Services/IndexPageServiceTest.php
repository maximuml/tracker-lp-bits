<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\IndexRepository;
use App\Services\IndexPageService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for IndexPageService.
 *
 * Covers build() with all sections disabled, individual section
 * visibility toggles, guest vs authenticated user, top-level key
 * structure, disclaimer, browser note, and tracker load.
 */
final class IndexPageServiceTest extends TestCase
{
    use DatabaseTransactions;

    private IndexPageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        if (! defined('IN_NEXUS')) {
            define('IN_NEXUS', true);
        }
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->service = new IndexPageService;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $values */
    private function mockGlobals(array $values = []): void
    {
        $globals = new Globals;
        foreach ($values as $key => $value) {
            $globals->set($key, $value);
        }
        $this->app->instance(Globals::class, $globals);
    }

    /** @param  array<string, mixed>  $userData */
    private function setCurrentUser(array $userData = []): void
    {
        $currentUser = new CurrentUser;
        $currentUser->set(array_merge([
            'id' => 0,
            'username' => '',
            'class' => 1,
        ], $userData));
        $this->app->instance(CurrentUser::class, $currentUser);
    }

    private function mockCache(): void
    {
        /** @var LegacyRedisCache&MockInterface $cache */
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldIgnoreMissing();
        $cache->shouldReceive('get_value')->andReturn(false);
        $cache->shouldReceive('delete_value')->andReturn(true);
        $cache->shouldReceive('cache_value')->andReturn(true);
        $this->app->instance(LegacyRedisCache::class, $cache);
    }

    /** @return IndexRepository&MockInterface */
    private function mockIndexRepo(): mixed
    {
        /** @var IndexRepository&MockInterface $repo */
        $repo = Mockery::mock(IndexRepository::class);
        $repo->shouldReceive('getLatestNews')->andReturn([]);
        $repo->shouldReceive('getLatestForumPosts')->andReturn([]);
        $repo->shouldReceive('getLatestTorrents')->andReturn(new Collection);
        $repo->shouldReceive('getTopUploaders')->andReturn(new Collection);
        $repo->shouldReceive('getCurrentPoll')->andReturn(null);
        $repo->shouldReceive('getUserVote')->andReturn(null);
        $repo->shouldReceive('getPollResults')->andReturn([]);
        $repo->shouldReceive('getUserStats')->andReturn([
            'registered' => 0,
            'unverified' => 0,
            'totalonlinetoday' => 0,
            'totalonlineweek' => 0,
            'vip' => 0,
            'donated' => 0,
            'warned' => 0,
            'disabled' => 0,
            'registered_male' => 0,
            'registered_female' => 0,
        ]);
        $repo->shouldReceive('getTorrentStats')->andReturn([
            'torrents' => 0,
            'dead' => 0,
            'seeders' => 0,
            'leechers' => 0,
            'peers' => 0,
            'ratio' => 0,
            'activewebusernow' => 0,
            'activetrackerusernow' => 0,
            'totaltorrentssize' => 0,
            'totaluploaded' => 0,
            'totaldownloaded' => 0,
            'totaldata' => 0,
        ]);
        $repo->shouldReceive('getClassStats')->andReturn([
            UC_PEASANT => 0,
            UC_USER => 0,
            UC_POWER_USER => 0,
            UC_ELITE_USER => 0,
            UC_CRAZY_USER => 0,
            UC_INSANE_USER => 0,
            UC_VETERAN_USER => 0,
            UC_EXTREME_USER => 0,
            UC_ULTIMATE_USER => 0,
            UC_NEXUS_MASTER => 0,
        ]);
        $this->app->instance(IndexRepository::class, $repo);

        return $repo;
    }

    /**
     * @param  array<string, mixed>  $globalsOverrides
     * @return array<string, mixed>
     */
    private function buildWithAllSectionsDisabled(array $globalsOverrides = []): array
    {
        $this->mockIndexRepo();
        $this->setCurrentUser();
        $this->mockCache();
        $this->mockGlobals(array_merge([
            'showshoutbox_main' => 'no',
            'showlastxforumposts_main' => 'no',
            'showlastxtorrents_main' => 'no',
            'showpolls_main' => 'no',
            'showstats_main' => 'no',
            'showtrackerload' => 'no',
            'maxnewsnum_main' => 0,
        ], $globalsOverrides));

        return $this->service->build();
    }

    // ─── Instantiation ────────────────────────────────────────────────

    public function test_can_instantiate_service(): void
    {
        $service = new IndexPageService;

        $this->assertInstanceOf(IndexPageService::class, $service);
    }

    // ─── build() top-level structure ──────────────────────────────────

    public function test_build_returns_expected_top_level_keys(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertArrayHasKey('lang', $result);
        $this->assertArrayHasKey('curUser', $result);
        $this->assertArrayHasKey('canNewsManage', $result);
        $this->assertArrayHasKey('canPollManage', $result);
        $this->assertArrayHasKey('canSbManage', $result);
        $this->assertArrayHasKey('canLog', $result);
        $this->assertArrayHasKey('news', $result);
        $this->assertArrayHasKey('shoutbox', $result);
        $this->assertArrayHasKey('forumPosts', $result);
        $this->assertArrayHasKey('latestTorrents', $result);
        $this->assertArrayHasKey('topUploaders', $result);
        $this->assertArrayHasKey('polls', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('trackerLoad', $result);
        $this->assertArrayHasKey('disclaimer', $result);
        $this->assertArrayHasKey('browserNote', $result);
        $this->assertArrayHasKey('extraModules', $result);
    }

    public function test_build_returns_cur_user_array(): void
    {
        $this->mockIndexRepo();
        $this->setCurrentUser(['id' => 99, 'username' => 'myuser']);
        $this->mockCache();
        $this->mockGlobals([
            'showshoutbox_main' => 'no',
            'showlastxforumposts_main' => 'no',
            'showlastxtorrents_main' => 'no',
            'showpolls_main' => 'no',
            'showstats_main' => 'no',
            'showtrackerload' => 'no',
        ]);

        $result = $this->service->build();

        $this->assertSame(99, (int) $result['curUser']['id']);
        $this->assertSame('myuser', $result['curUser']['username']);
    }

    // ─── Section visibility toggles ───────────────────────────────────

    public function test_shoutbox_hidden_when_showshoutbox_main_is_no(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result['shoutbox']['show']);
    }

    public function test_shoutbox_shown_when_showshoutbox_main_is_yes(): void
    {
        $result = $this->buildWithAllSectionsDisabled([
            'showshoutbox_main' => 'yes',
        ]);

        $this->assertTrue($result['shoutbox']['show']);
        $this->assertArrayHasKey('title', $result['shoutbox']);
        $this->assertArrayHasKey('toolbar', $result['shoutbox']);
    }

    public function test_forum_posts_hidden_when_setting_is_no(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result['forumPosts']['show']);
    }

    public function test_forum_posts_hidden_when_no_current_user(): void
    {
        $this->mockIndexRepo();
        $this->setCurrentUser(['id' => 0]);
        $this->mockCache();
        $this->mockGlobals([
            'showshoutbox_main' => 'no',
            'showlastxforumposts_main' => 'yes',
            'showlastxtorrents_main' => 'no',
            'showpolls_main' => 'no',
            'showstats_main' => 'no',
            'showtrackerload' => 'no',
        ]);

        $result = $this->service->build();

        // Empty curUser means forum posts should not show
        $this->assertFalse($result['forumPosts']['show']);
    }

    public function test_latest_torrents_hidden_when_setting_is_no(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result['latestTorrents']['show']);
    }

    public function test_latest_torrents_shown_when_setting_is_yes(): void
    {
        $result = $this->buildWithAllSectionsDisabled([
            'showlastxtorrents_main' => 'yes',
        ]);

        $this->assertTrue($result['latestTorrents']['show']);
    }

    public function test_polls_hidden_when_setting_is_no(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result['polls']['show']);
    }

    public function test_stats_hidden_when_setting_is_no(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result['stats']['show']);
    }

    public function test_stats_shown_when_setting_is_yes(): void
    {
        $result = $this->buildWithAllSectionsDisabled([
            'showstats_main' => 'yes',
        ]);

        $this->assertTrue($result['stats']['show']);
        $this->assertArrayHasKey('userStats', $result['stats']);
        $this->assertArrayHasKey('torrentStats', $result['stats']);
        $this->assertArrayHasKey('classStats', $result['stats']);
    }

    public function test_tracker_load_hidden_when_setting_is_no(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result['trackerLoad']['show']);
    }

    public function test_tracker_load_shown_when_setting_is_yes(): void
    {
        $result = $this->buildWithAllSectionsDisabled([
            'showtrackerload' => 'yes',
        ]);

        $this->assertTrue($result['trackerLoad']['show']);
        $this->assertArrayHasKey('load', $result['trackerLoad']);
    }

    // ─── Always-on sections ───────────────────────────────────────────

    public function test_disclaimer_always_shown(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertTrue($result['disclaimer']['show']);
        $this->assertArrayHasKey('title', $result['disclaimer']);
        $this->assertArrayHasKey('content', $result['disclaimer']);
    }

    public function test_browser_note_always_shown(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertTrue($result['browserNote']['show']);
        $this->assertArrayHasKey('note', $result['browserNote']);
    }

    public function test_news_always_shown(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertTrue($result['news']['show']);
        $this->assertArrayHasKey('items', $result['news']);
        $this->assertArrayHasKey('canManage', $result['news']);
    }

    public function test_top_uploaders_hidden_when_setting_disabled(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result['topUploaders']['show']);
    }

    // ─── Permission flags ─────────────────────────────────────────────

    public function test_build_permission_flags_are_false_for_guest(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result['canNewsManage']);
        $this->assertFalse($result['canPollManage']);
        $this->assertFalse($result['canSbManage']);
        $this->assertFalse($result['canLog']);
    }
}
