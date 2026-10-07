<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Offer;

use App\Contracts\Repositories\OfferCommentRepositoryInterface;
use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\UsercpRepositoryInterface;
use App\Services\Offer\OfferListBuilder;
use App\Support\Cache\NexusCache;
use App\Support\CurrentUser;
use App\Support\Time;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Kills escaped mutants in the offer-list builder (formerly
 * OfferPageService::buildOfferList): sort whitelist + order flip,
 * last-comment cache hit/miss paths, tooltip render cache, new-comment
 * flag vs last_offer, row badges, updateLastOffer side effect.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class OfferListBuilderTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLegacySettings;

    /** @var OfferRepositoryInterface&MockInterface */
    private OfferRepositoryInterface $offerRepo;

    /** @var OfferCommentRepositoryInterface&MockInterface */
    private OfferCommentRepositoryInterface $commentRepo;

    /** @var UsercpRepositoryInterface&MockInterface */
    private UsercpRepositoryInterface $usercpRepo;

    /** @var NexusCache&MockInterface */
    private NexusCache $cache;

    private int $initialObLevel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialObLevel = ob_get_level();
        Redis::connection()->flushdb();

        /** @var OfferRepositoryInterface&MockInterface $offerRepo */
        $offerRepo = Mockery::mock(OfferRepositoryInterface::class);
        $this->offerRepo = $offerRepo;
        /** @var OfferCommentRepositoryInterface&MockInterface $commentRepo */
        $commentRepo = Mockery::mock(OfferCommentRepositoryInterface::class);
        $this->commentRepo = $commentRepo;
        /** @var UsercpRepositoryInterface&MockInterface $usercpRepo */
        $usercpRepo = Mockery::mock(UsercpRepositoryInterface::class);
        $this->usercpRepo = $usercpRepo;
        $this->usercpRepo->shouldReceive('updateLastOffer')->andReturn(true)->byDefault();
        /** @var NexusCache&MockInterface $cache */
        $cache = Mockery::mock(NexusCache::class);
        $this->cache = $cache;
        $this->cache->shouldIgnoreMissing();
        $this->cache->shouldReceive('get')->andReturn(false)->byDefault();
        $this->cache->shouldReceive('getMany')->andReturn([])->byDefault();

        $currentUser = new CurrentUser;
        $currentUser->set(['id' => 7, 'username' => 'u', 'class' => 0]);
        $this->app->instance(CurrentUser::class, $currentUser);

        $_SERVER['PHP_SELF'] = '/web/offers';

        DB::table('categories')->insert([
            'mode' => 1, 'class_name' => 'test_cat', 'name' => 'ZZ Category', 'image' => 'x.gif', 'sort_index' => 0,
        ]);

        $this->seedTestSettings([
            'BASEURL' => 'http://test.com',
            'browsecatmode' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }
        Mockery::close();
        parent::tearDown();
    }

    private function builder(): OfferListBuilder
    {
        return new OfferListBuilder(
            $this->offerRepo,
            $this->commentRepo,
            $this->cache,
            $this->usercpRepo,
        );
    }

    /** @param  array<string, mixed>  $overrides
     * @return array<string, mixed> */
    private function globalData(array $overrides = []): array
    {
        return array_merge([
            'uploadClass' => 0,
            'addofferClass' => 0,
            'minoffervotes' => 5,
            'offervotetimeoutMain' => 0,
            'offeruptimeoutMain' => 0,
            'browsecatmode' => 1,
            'againstofferClass' => 0,
        ], $overrides);
    }

    /** @param  array<string, mixed>  $query */
    private function request(array $query = []): Request
    {
        return Request::create('/web/offers?'.http_build_query($query), 'GET');
    }

    /** @param  array<int, array<string, mixed>>  $rows
     * @return array{count: int, rows: Collection<int, \stdClass>} */
    private function listResult(array $rows, int $count = 1): array
    {
        return ['count' => $count, 'rows' => collect(array_map(static fn (array $r) => (object) $r, $rows))];
    }

    /** @param  array<string, mixed>  $overrides
     * @return array<string, mixed> */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'id' => 5,
            'userid' => 10,
            'name' => 'An Offer',
            'added' => '2024-01-02 03:04:05',
            'allowedtime' => '2024-01-03 03:04:05',
            'comments' => 0,
            'yeah' => 0,
            'against' => 0,
            'cat_id' => 1,
            'allowed' => 1,
            'image' => 'x.gif',
            'cat' => 'Movies',
        ], $overrides);
    }

    /** @param  array<string, mixed>  $overrides
     * @return array<string, mixed> */
    private function curUser(array $overrides = []): array
    {
        return array_merge(['id' => 7, 'timetype' => 1, 'appendnew' => 'yes', 'last_offer' => '2000-01-01 00:00:00'], $overrides);
    }

    private function assertAbortContains(callable $fn, string $needle): void
    {
        set_error_handler(static fn (int $severity): bool => true, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);
        try {
            $fn();
            $this->fail('Expected abort');
        } catch (HttpResponseException $e) {
            $this->assertStringContainsString(e($needle), (string) $e->getResponse()->getContent());
        } finally {
            restore_error_handler();
        }
    }

    public function test_invalid_sort_aborts(): void
    {
        $this->assertAbortContains(
            fn () => $this->builder()->build($this->curUser(), 7, $this->request(['sort' => 'evil']), $this->globalData()),
            (string) __('offers.std_smell_rat'),
        );
    }

    public function test_sort_name_desc_flips_to_asc_order_clause(): void
    {
        $this->offerRepo->shouldReceive('getLegacyList')
            ->twice()
            ->with(0, 0, '', ' ORDER BY name asc', 'desc', Mockery::type('int'), Mockery::type('int'))
            ->andReturn($this->listResult([], 0));

        $s = $this->builder()->build($this->curUser(), 7, $this->request(['sort' => 'name', 'type' => 'desc']), $this->globalData());

        $this->assertSame(0, $s->count);
        $this->assertNull($s->table);
    }

    public function test_vote_sorts_pass_empty_sort_column(): void
    {
        // Legacy quirk: sort=yeah/against select a whitelist value but build
        // no ORDER BY clause.
        $this->offerRepo->shouldReceive('getLegacyList')
            ->twice()
            ->with(0, 0, '', '', 'asc', Mockery::type('int'), Mockery::type('int'))
            ->andReturn($this->listResult([], 0));

        $this->builder()->build($this->curUser(), 7, $this->request(['sort' => 'yeah', 'type' => 'asc']), $this->globalData());
        $this->addToAssertionCount(1);
    }

    public function test_category_and_search_filters_forwarded(): void
    {
        $this->offerRepo->shouldReceive('getLegacyList')
            ->twice()
            ->with(3, 42, 'needle', '', 'desc', Mockery::type('int'), Mockery::type('int'))
            ->andReturn($this->listResult([], 0));

        $s = $this->builder()->build(
            $this->curUser(),
            7,
            $this->request(['category' => 3, 'offerorid' => 42, 'search' => 'needle']),
            $this->globalData(),
        );

        $this->assertSame(0, $s->count);
    }

    public function test_empty_result_renders_empty_state_and_no_table(): void
    {
        $this->offerRepo->shouldReceive('getLegacyList')->andReturn($this->listResult([], 0));
        $this->usercpRepo->shouldReceive('updateLastOffer')->with(7)->once();

        $s = $this->builder()->build($this->curUser(), 7, $this->request(), $this->globalData());

        $this->assertSame(0, $s->count);
        $this->assertNull($s->table);
        $this->assertStringContainsString((string) __('offers.text_nothing_found'), (string) $s->emptyState);
        $this->assertNotEmpty($s->categories);
    }

    public function test_row_mapping_badges_votes_and_new_flag(): void
    {
        $this->offerRepo->shouldReceive('getLegacyList')->andReturn($this->listResult([
            $this->row(['id' => 1, 'allowed' => 0, 'yeah' => 3, 'against' => 1]),
            $this->row(['id' => 2, 'allowed' => 2, 'name' => str_repeat('x', 80)]),
        ], 2));

        $s = $this->builder()->build($this->curUser(), 7, $this->request(), $this->globalData());

        $table = $s->table;
        $this->assertNotNull($table);
        $this->assertCount(2, $table->rows);

        [$allowedRow, $deniedRow] = $table->rows;
        $this->assertSame('nx-color-green', $allowedRow->allowed->cssClass);
        $this->assertNotNull($allowedRow->voteResults);
        $this->assertSame(3, $allowedRow->voteResults->yeah);
        $this->assertTrue($allowedRow->isNew); // appendnew=yes + added >= last_offer
        $this->assertSame('nx-color-red', $deniedRow->allowed->cssClass);
        $this->assertNull($deniedRow->voteResults); // 0/0 votes → no link
        $this->assertSame(str_repeat('x', 68).'..', $deniedRow->displayName); // 70-char truncation
    }

    public function test_last_comments_cached_and_uncached_paths(): void
    {
        $this->offerRepo->shouldReceive('getLegacyList')->andReturn($this->listResult([
            $this->row(['id' => 1, 'comments' => 2]),
            $this->row(['id' => 2, 'comments' => 1]),
            $this->row(['id' => 3, 'comments' => 0]),
        ], 3));

        // offer 1 cached, offer 2 needs DB fetch; post_9-style tt cache miss
        $this->cache->shouldReceive('getMany')->andReturnUsing(function (array $keys) {
            if (in_array('offer_1_last_comment_content', $keys, true)) {
                return ['offer_1_last_comment_content' => ['user' => 9, 'added' => '2024-06-01 00:00:00', 'text' => 'cached comment']];
            }

            return [];
        });
        $this->commentRepo->shouldReceive('getLastComments')->once()->with([2])->andReturn([
            2 => ['user' => 7, 'added' => '2024-06-02 00:00:00', 'text' => 'fresh comment'],
        ]);
        $this->cache->shouldReceive('put')->with('offer_2_last_comment_content', Mockery::type('array'), 1855)->once();
        $this->cache->shouldReceive('put')->with('offer_1_last_comment_content', Mockery::any(), Mockery::any())->never();

        $s = $this->builder()->build($this->curUser(), 7, $this->request(), $this->globalData());

        $table = $s->table;
        $this->assertNotNull($table);
        $this->assertCount(3, $table->rows);
        [$a, $b, $c] = $table->rows;

        // comments>0 → details link + hasNew (other user + added >= last_offer)
        $this->assertSame(2, $a->comment->count);
        $this->assertTrue($a->comment->hasNew);
        $this->assertStringContainsString('off_details=1', (string) $a->comment->href);

        // last comment by me → not new
        $this->assertFalse($b->comment->hasNew);

        // zero comments → add-comment link
        $this->assertSame(0, $c->comment->count);
        $this->assertStringContainsString('action=add', (string) $c->comment->href);
    }

    public function test_timealive_user_sees_formatted_lastcom_tooltip(): void
    {
        $this->offerRepo->shouldReceive('getLegacyList')->andReturn($this->listResult([
            $this->row(['id' => 1, 'comments' => 2]),
        ], 1));
        $this->cache->shouldReceive('getMany')->andReturn([
            'offer_1_last_comment_content' => ['user' => 9, 'added' => '2024-06-01 00:00:00', 'text' => 'cached comment'],
        ]);

        $s = $this->builder()->build($this->curUser(['timetype' => 1]), 7, $this->request(), $this->globalData());

        $this->assertNotNull($s->table);
        $tooltip = $s->table->tooltips[0] ?? null;
        $this->assertNotNull($tooltip);
        $this->assertStringContainsString(
            (string) __('offers.text_blank').Time::format('2024-06-01 00:00:00', true, false, true),
            (string) $tooltip->content,
        );
    }

    public function test_lastcom_tooltips_batch_fetched_and_cached_under_fmt_tt_keys(): void
    {
        $this->offerRepo->shouldReceive('getLegacyList')->andReturn($this->listResult([
            $this->row(['id' => 1, 'comments' => 1]),
        ], 1));

        $lastcom = ['user' => 9, 'added' => '2024-06-01 00:00:00', 'text' => 'cached comment'];
        $fmtTtCalls = [];
        $puts = [];
        $this->cache->shouldReceive('getMany')->andReturnUsing(function (array $keys) use ($lastcom, &$fmtTtCalls) {
            if ($keys === ['offer_1_last_comment_content']) {
                return ['offer_1_last_comment_content' => $lastcom];
            }
            $fmtTtCalls[] = $keys;

            return [];
        });
        $this->cache->shouldReceive('put')->andReturnUsing(function (string $key, mixed $value, int $ttl) use (&$puts) {
            $puts[] = [$key, $ttl];
        });

        $this->builder()->build($this->curUser(), 7, $this->request(), $this->globalData());

        $this->assertCount(1, $fmtTtCalls);
        $this->assertStringStartsWith('fmt_tt_', (string) $fmtTtCalls[0][0]);

        $fmtPuts = array_values(array_filter($puts, static fn (array $p): bool => str_starts_with($p[0], 'fmt_tt_')));
        $this->assertCount(1, $fmtPuts);
        $this->assertSame(86400, $fmtPuts[0][1]);
    }

    public function test_showlastcom_disabled_uses_title_tooltips(): void
    {
        $this->offerRepo->shouldReceive('getLegacyList')->andReturn($this->listResult([
            $this->row(['id' => 1, 'comments' => 2]),
        ]));
        $this->cache->shouldReceive('getMany')->andReturn([
            'offer_1_last_comment_content' => ['user' => 9, 'added' => '2024-06-01 00:00:00', 'text' => 'x'],
        ]);

        $s = $this->builder()->build($this->curUser(['showlastcom' => false]), 7, $this->request(), $this->globalData());

        $table = $s->table;
        $this->assertNotNull($table);
        $this->assertSame([], $table->tooltips);
        $this->assertSame((string) __('offers.title_has_new_comment'), $table->rows[0]->comment->title);
    }

    public function test_appendnew_no_marks_nothing_new(): void
    {
        $this->offerRepo->shouldReceive('getLegacyList')->andReturn($this->listResult([$this->row()]));

        $s = $this->builder()->build($this->curUser(['appendnew' => 'no']), 7, $this->request(), $this->globalData());

        $table = $s->table;
        $this->assertNotNull($table);
        $this->assertFalse($table->rows[0]->isNew);
    }

    public function test_timeout_column_hidden_without_both_timeouts(): void
    {
        $this->offerRepo->shouldReceive('getLegacyList')->andReturn($this->listResult([$this->row()]));

        $s = $this->builder()->build($this->curUser(), 7, $this->request(), $this->globalData(['offervotetimeoutMain' => 3600]));

        $table = $s->table;
        $this->assertNotNull($table);
        $this->assertFalse($table->showTimeout);
        $this->assertSame('N/A', (string) $table->rows[0]->timeout);
    }

    public function test_timeout_computed_for_pending_and_allowed(): void
    {
        $this->offerRepo->shouldReceive('getLegacyList')->andReturn($this->listResult([
            $this->row(['id' => 1, 'allowed' => 1, 'added' => '2024-01-01 00:00:00']),
            $this->row(['id' => 2, 'allowed' => 0, 'allowedtime' => '2024-01-02 00:00:00']),
        ], 2));

        $s = $this->builder()->build(
            $this->curUser(),
            7,
            $this->request(),
            $this->globalData(['offervotetimeoutMain' => 3600, 'offeruptimeoutMain' => 7200]),
        );

        $table = $s->table;
        $this->assertNotNull($table);
        $this->assertTrue($table->showTimeout);
        $this->assertNotSame('N/A', (string) $table->rows[0]->timeout);
        $this->assertNotSame('N/A', (string) $table->rows[1]->timeout);
    }
}
