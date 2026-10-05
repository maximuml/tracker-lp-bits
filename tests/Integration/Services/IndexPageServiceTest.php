<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\IndexRepository;
use App\Services\IndexPageService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\ViewModels\Index\IndexBrowserNoteSection;
use App\ViewModels\Index\IndexClassStatRow;
use App\ViewModels\Index\IndexDisclaimerSection;
use App\ViewModels\Index\IndexForumPostsSection;
use App\ViewModels\Index\IndexLatestTorrentsSection;
use App\ViewModels\Index\IndexNewsItem;
use App\ViewModels\Index\IndexNewsSection;
use App\ViewModels\Index\IndexPollsSection;
use App\ViewModels\Index\IndexPollsSectionFactory;
use App\ViewModels\Index\IndexShoutboxSection;
use App\ViewModels\Index\IndexStatsSection;
use App\ViewModels\Index\IndexTopUploadersSection;
use App\ViewModels\IndexPageViewModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Unit tests for IndexPageService.
 *
 * Covers build() with all sections disabled, individual section
 * visibility toggles, guest vs authenticated user, top-level key
 * structure, disclaimer, browser note, and tracker load.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class IndexPageServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLegacySettings;

    private IndexPageService $service;

    private CurrentUser $currentUser;

    private Globals $globals;

    private LegacyRedisCache $cache;

    /** @var IndexRepository&MockInterface */
    private IndexRepository $indexRepository;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->currentUser = new CurrentUser;
        $this->globals = new Globals;
        $this->cache = new LegacyRedisCache;

        /** @var IndexRepository&MockInterface $repo */
        $repo = Mockery::mock(IndexRepository::class);
        $this->indexRepository = $repo;

        $this->service = new IndexPageService(
            $this->currentUser,
            $this->cache,
            $this->indexRepository,
            new IndexPollsSectionFactory($this->cache, $this->indexRepository),
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $values */
    private function mockGlobals(array $values = []): void
    {
        $this->seedTestSettings($values, $this->globals);
    }

    /** @param  array<string, mixed>  $userData */
    private function setCurrentUser(array $userData = []): void
    {
        $this->currentUser->set(array_merge([
            'id' => 0,
            'username' => '',
            'class' => 1,
        ], $userData));
    }

    private function mockCache(): void {}

    /** @return IndexRepository&MockInterface */
    private function mockIndexRepo(): mixed
    {
        $this->indexRepository->shouldReceive('getLatestNews')->andReturn([]);
        $this->indexRepository->shouldReceive('getLatestForumPosts')->andReturn([]);
        $this->indexRepository->shouldReceive('getLatestTorrents')->andReturn(new Collection);
        $this->indexRepository->shouldReceive('getTopUploaders')->andReturn(new Collection);
        $this->indexRepository->shouldReceive('getCurrentPoll')->andReturn(null);
        $this->indexRepository->shouldReceive('getUserVote')->andReturn(null);
        $this->indexRepository->shouldReceive('getPollResults')->andReturn([]);
        $this->indexRepository->shouldReceive('getUserStats')->andReturn([
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
        $this->indexRepository->shouldReceive('getTorrentStats')->andReturn([
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
        $this->indexRepository->shouldReceive('getTodayActiveUsers')->andReturn([
            'count' => 0,
            'ids' => [],
        ]);
        $this->indexRepository->shouldReceive('getClassStats')->andReturn([
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

        return $this->indexRepository;
    }

    /**
     * @param  array<string, mixed>  $globalsOverrides
     */
    private function buildWithAllSectionsDisabled(array $globalsOverrides = []): IndexPageViewModel
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
        $service = new IndexPageService(
            $this->currentUser,
            $this->cache,
            $this->indexRepository,
            new IndexPollsSectionFactory($this->cache, $this->indexRepository),
        );

        $this->assertInstanceOf(IndexPageService::class, $service);
    }

    // ─── build() top-level structure ──────────────────────────────────

    public function test_build_returns_expected_top_level_keys(): void
    {
        $result = $this->buildWithAllSectionsDisabled();
        $this->assertInstanceOf(IndexNewsSection::class, $result->news);
        $this->assertInstanceOf(IndexShoutboxSection::class, $result->shoutbox);
        $this->assertInstanceOf(IndexForumPostsSection::class, $result->forumPosts);
        $this->assertInstanceOf(IndexLatestTorrentsSection::class, $result->latestTorrents);
        $this->assertInstanceOf(IndexTopUploadersSection::class, $result->topUploaders);
        $this->assertInstanceOf(IndexPollsSection::class, $result->polls);
        $this->assertInstanceOf(IndexStatsSection::class, $result->stats);
        $this->assertInstanceOf(IndexDisclaimerSection::class, $result->disclaimer);
        $this->assertInstanceOf(IndexBrowserNoteSection::class, $result->browserNote);
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

        $this->assertSame(99, (int) $result->curUser['id']);
        $this->assertSame('myuser', $result->curUser['username']);
    }

    // ─── Section visibility toggles ───────────────────────────────────

    public function test_shoutbox_hidden_when_showshoutbox_main_is_no(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result->shoutbox->show);
    }

    public function test_shoutbox_shown_when_showshoutbox_main_is_yes(): void
    {
        $result = $this->buildWithAllSectionsDisabled([
            'showshoutbox_main' => 'yes',
        ]);

        $this->assertTrue($result->shoutbox->show);
        $this->assertNotEmpty($result->shoutbox->title);
        $this->assertNotNull($result->shoutbox->toolbar);
    }

    public function test_forum_posts_hidden_when_setting_is_no(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result->forumPosts->show);
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
        $this->assertFalse($result->forumPosts->show);
    }

    public function test_latest_torrents_hidden_when_setting_is_no(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result->latestTorrents->show);
    }

    public function test_latest_torrents_shown_when_setting_is_yes(): void
    {
        $result = $this->buildWithAllSectionsDisabled([
            'showlastxtorrents_main' => 'yes',
        ]);

        $this->assertTrue($result->latestTorrents->show);
    }

    public function test_latest_torrents_title_does_not_mismatch_card_count(): void
    {
        $torrent = (new Torrent)->forceFill([
            'id' => 42,
            'name' => 'Some Torrent Name',
            'cover' => '',
            'anonymous' => 'yes',
            'owner' => 0,
            'seeders' => 3,
            'leechers' => 1,
            'size' => 1024,
        ]);
        $torrent->setRelation('basic_category', (new Category)->forceFill(['name' => 'Movies']));

        // Declared before mockIndexRepo() so it wins over the generic stub;
        // a call with any other limit falls through and yields zero cards.
        $this->indexRepository->shouldReceive('getLatestTorrents')
            ->with(12)
            ->andReturn(new Collection([$torrent]));

        $result = $this->buildWithAllSectionsDisabled([
            'showlastxtorrents_main' => 'yes',
        ]);

        $html = (string) $result->latestTorrents->html;
        $this->assertStringContainsString('Latest Torrents', $html);
        $this->assertStringNotContainsString('Last 5', $html);
        $this->assertSame(1, substr_count($html, 'class="lt-card"'));
        $this->assertStringContainsString('lt-cover-empty', $html);
    }

    public function test_polls_hidden_when_setting_is_no(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result->polls->show);
    }

    public function test_stats_hidden_when_setting_is_no(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result->stats->show);
    }

    public function test_stats_shown_when_setting_is_yes(): void
    {
        $result = $this->buildWithAllSectionsDisabled([
            'showstats_main' => 'yes',
        ]);

        $this->assertTrue($result->stats->show);
        $this->assertIsArray($result->stats->userStats);
        $this->assertIsArray($result->stats->torrentStats);
        $this->assertContainsOnlyInstancesOf(IndexClassStatRow::class, $result->stats->classStats);
    }

    // ─── Always-on sections ───────────────────────────────────────────

    public function test_disclaimer_always_shown(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertTrue($result->disclaimer->show);
        $this->assertNotEmpty($result->disclaimer->title);
        $this->assertNotEmpty($result->disclaimer->content);
    }

    public function test_browser_note_always_shown(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertTrue($result->browserNote->show);
    }

    public function test_news_always_shown(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertTrue($result->news->show);
        $this->assertContainsOnlyInstancesOf(IndexNewsItem::class, $result->news->items);
    }

    public function test_top_uploaders_hidden_when_setting_disabled(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result->topUploaders->show);
    }

    // ─── Permission flags ─────────────────────────────────────────────

    public function test_build_permission_flags_are_false_for_guest(): void
    {
        $result = $this->buildWithAllSectionsDisabled();

        $this->assertFalse($result->canNewsManage);
        $this->assertFalse($result->canPollManage);
        $this->assertFalse($result->canSbManage);
        $this->assertFalse($result->canLog);
    }
}
