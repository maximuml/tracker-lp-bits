<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Offer;

use App\Contracts\Repositories\OfferCommentRepositoryInterface;
use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\OfferVoteRepositoryInterface;
use App\Models\Offer;
use App\Services\Offer\OfferDetailsBuilder;
use App\Support\Cache\NexusCache;
use App\Support\CurrentUser;
use App\Support\Time;
use App\ViewModels\Offer\OfferDetailsViewModel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Kills escaped mutants in the offer-details builder (formerly
 * OfferPageService::buildOffDetails): aborts on missing id/offer, allowed
 * badge per status, allowedNote owner-vs-voter branches, descr cache
 * hit/miss, comment pager, vote counts, edit/delete visibility.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class OfferDetailsBuilderTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLegacySettings;

    /** @var OfferRepositoryInterface&MockInterface */
    private OfferRepositoryInterface $offerRepo;

    /** @var OfferVoteRepositoryInterface&MockInterface */
    private OfferVoteRepositoryInterface $voteRepo;

    /** @var OfferCommentRepositoryInterface&MockInterface */
    private OfferCommentRepositoryInterface $commentRepo;

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
        /** @var OfferVoteRepositoryInterface&MockInterface $voteRepo */
        $voteRepo = Mockery::mock(OfferVoteRepositoryInterface::class);
        $this->voteRepo = $voteRepo;
        /** @var OfferCommentRepositoryInterface&MockInterface $commentRepo */
        $commentRepo = Mockery::mock(OfferCommentRepositoryInterface::class);
        $this->commentRepo = $commentRepo;
        /** @var NexusCache&MockInterface $cache */
        $cache = Mockery::mock(NexusCache::class);
        $this->cache = $cache;
        $this->cache->shouldIgnoreMissing();
        $this->cache->shouldReceive('get')->andReturn(false)->byDefault();

        $currentUser = new CurrentUser;
        $currentUser->set(['id' => 7, 'username' => 'u', 'class' => 0]);
        $this->app->instance(CurrentUser::class, $currentUser);

        $this->seedTestSettings(['BASEURL' => 'http://test.com']);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Calls build() while suppressing legacy error handlers left by
     * inner helpers (Format::formatComment / view fragments).
     */
    /** @param  array<string, mixed>  $curUser */
    private function callBuild(array $curUser = ['id' => 7], ?Request $request = null): OfferDetailsViewModel
    {
        set_error_handler(static fn (int $severity): bool => true, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);

        try {
            return $this->builder()->build($curUser, 7, $request ?? $this->request());
        } finally {
            restore_error_handler();
        }
    }

    private function builder(): OfferDetailsBuilder
    {
        return new OfferDetailsBuilder(
            $this->offerRepo,
            $this->voteRepo,
            $this->commentRepo,
            $this->cache,
        );
    }

    /** @param  array<string, mixed>  $attributes */
    private function offer(array $attributes = []): Offer
    {
        return (new Offer)->setRawAttributes(array_merge([
            'id' => 5,
            'userid' => 10,
            'name' => 'My Offer',
            'descr' => 'descr text',
            'added' => '2024-01-02 03:04:05',
            'allowed' => 1,
            'yeah' => 0,
            'against' => 0,
        ], $attributes));
    }

    /** @param  array<string, mixed>  $query */
    private function request(array $query = ['id' => 5]): Request
    {
        return Request::create('/web/offers?'.http_build_query($query), 'GET');
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

    public function test_zero_id_aborts(): void
    {
        $this->assertAbortContains(fn () => $this->callBuild(request: $this->request(['id' => 0])), (string) __('offers.std_smell_rat'));
    }

    public function test_missing_offer_aborts(): void
    {
        $this->offerRepo->shouldReceive('findOffer')->with(5)->andReturn(null);

        $this->assertAbortContains(fn () => $this->callBuild(), (string) __('offers.text_nothing_found'));
    }

    public function test_pending_offer_badge_and_note(): void
    {
        $this->offerRepo->shouldReceive('findOffer')->andReturn($this->offer(['allowed' => 1]));
        $this->voteRepo->shouldReceive('getVoteCounts')->with(5)->andReturn(['yeah' => 4, 'against' => 2]);
        $this->commentRepo->shouldReceive('countComments')->with(5)->andReturn(0);

        $s = $this->builder()->build(['id' => 7, 'timetype' => 1], 7, $this->request());

        $this->assertTrue($s->isPending);
        $this->assertSame('nx-color-red', $s->status->cssClass);
        $this->assertSame((string) __('offers.text_pending'), $s->status->label);
        $this->assertSame(4, $s->yeah);
        $this->assertSame(2, $s->against);
        $this->assertSame('', $s->allowedNote);
        $this->assertSame('', (string) $s->pagerTop);
        $this->assertSame('', (string) $s->pagerBottom);
        $this->assertSame(
            (string) __('offers.text_blank').Time::format('2024-01-02 03:04:05', true, false),
            (string) $s->offerTime,
        );
    }

    public function test_non_timealive_user_gets_at_prefix(): void
    {
        $this->offerRepo->shouldReceive('findOffer')->andReturn($this->offer(['allowed' => 1]));
        $this->voteRepo->shouldReceive('getVoteCounts')->andReturn(['yeah' => 0, 'against' => 0]);
        $this->commentRepo->shouldReceive('countComments')->andReturn(0);

        $s = $this->builder()->build(['id' => 7, 'timetype' => 0], 7, $this->request());

        $this->assertSame(
            (string) __('offers.text_at').Time::format('2024-01-02 03:04:05', true, false),
            (string) $s->offerTime,
        );
    }

    public function test_allowed_offer_owner_gets_urge_upload_note(): void
    {
        $this->offerRepo->shouldReceive('findOffer')->andReturn($this->offer(['allowed' => 0, 'userid' => 7]));
        $this->voteRepo->shouldReceive('getVoteCounts')->andReturn(['yeah' => 0, 'against' => 0]);
        $this->commentRepo->shouldReceive('countComments')->andReturn(0);

        $s = $this->builder()->build(['id' => 7], 7, $this->request());

        $this->assertFalse($s->isPending);
        $this->assertSame((string) __('offers.text_allowed'), $s->status->label);
        $this->assertSame('nx-color-green', $s->status->cssClass);
        $this->assertSame((string) __('offers.text_urge_upload_offer_note'), $s->allowedNote);
        $this->assertTrue($s->showEditDelete);
    }

    public function test_allowed_offer_voter_gets_pm_note_and_no_edit(): void
    {
        $this->offerRepo->shouldReceive('findOffer')->andReturn($this->offer(['allowed' => 0, 'userid' => 10]));
        $this->voteRepo->shouldReceive('getVoteCounts')->andReturn(['yeah' => 0, 'against' => 0]);
        $this->commentRepo->shouldReceive('countComments')->andReturn(0);

        $s = $this->builder()->build(['id' => 7], 7, $this->request());

        $this->assertSame((string) __('offers.text_voter_receives_pm_note'), $s->allowedNote);
        $this->assertFalse($s->showEditDelete);
    }

    public function test_denied_offer_shows_denied_badge(): void
    {
        $this->offerRepo->shouldReceive('findOffer')->andReturn($this->offer(['allowed' => 2]));
        $this->voteRepo->shouldReceive('getVoteCounts')->andReturn(['yeah' => 0, 'against' => 0]);
        $this->commentRepo->shouldReceive('countComments')->andReturn(0);

        $s = $this->builder()->build(['id' => 7], 7, $this->request());

        $this->assertSame('nx-color-red', $s->status->cssClass);
        $this->assertSame((string) __('offers.text_denied'), $s->status->label);
    }

    public function test_descr_format_cache_miss_then_hit(): void
    {
        $this->offerRepo->shouldReceive('findOffer')->andReturn($this->offer(['descr' => 'hello [b]world[/b]']));
        $this->voteRepo->shouldReceive('getVoteCounts')->andReturn(['yeah' => 0, 'against' => 0]);
        $this->commentRepo->shouldReceive('countComments')->andReturn(0);

        $key = 'fmt_offer_'.md5('hello [b]world[/b]');
        // Sequential answers on the same key: miss → render+cache, then hit → verbatim.
        $this->cache->shouldReceive('get')->with($key)->andReturn(false, '<b>cached</b>');
        $this->cache->shouldReceive('put')->with($key, Mockery::type('string'), 86400)->once();

        $s = $this->callBuild();
        $this->assertNotEmpty((string) $s->description);

        $s = $this->callBuild();
        $this->assertSame('<b>cached</b>', (string) $s->description);
    }

    public function test_empty_descr_skips_formatting(): void
    {
        $this->offerRepo->shouldReceive('findOffer')->andReturn($this->offer(['descr' => '']));
        $this->voteRepo->shouldReceive('getVoteCounts')->andReturn(['yeah' => 0, 'against' => 0]);
        $this->commentRepo->shouldReceive('countComments')->andReturn(0);
        $this->cache->shouldReceive('put')->never();

        $s = $this->builder()->build(['id' => 7], 7, $this->request());
        $this->assertSame('', (string) $s->description);
    }

    public function test_comment_count_builds_pager(): void
    {
        $this->offerRepo->shouldReceive('findOffer')->andReturn($this->offer());
        $this->voteRepo->shouldReceive('getVoteCounts')->andReturn(['yeah' => 0, 'against' => 0]);
        $this->commentRepo->shouldReceive('countComments')->with(5)->andReturn(30);

        $s = $this->builder()->build(['id' => 7], 7, $this->request());

        $this->assertSame(30, $s->commentCount);
        $this->assertNotEmpty((string) $s->pagerTop);
        $this->assertStringContainsString('off_details=1', (string) $s->pagerTop);
    }
}
