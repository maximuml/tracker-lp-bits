<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Offer;

use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\OfferVoteRepositoryInterface;
use App\Services\Offer\OfferVoteListBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Kills escaped mutants in the vote-list builder (formerly
 * OfferPageService::buildOfferVoteList): offer name lookup, vote-count
 * pager, vote enum mapping, username resolution, HTML-escaped name.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class OfferVoteListBuilderTest extends TestCase
{
    use DatabaseTransactions;

    /** @var OfferRepositoryInterface&MockInterface */
    private OfferRepositoryInterface $offerRepo;

    /** @var OfferVoteRepositoryInterface&MockInterface */
    private OfferVoteRepositoryInterface $voteRepo;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();

        /** @var OfferRepositoryInterface&MockInterface $offerRepo */
        $offerRepo = Mockery::mock(OfferRepositoryInterface::class);
        $this->offerRepo = $offerRepo;
        /** @var OfferVoteRepositoryInterface&MockInterface $voteRepo */
        $voteRepo = Mockery::mock(OfferVoteRepositoryInterface::class);
        $this->voteRepo = $voteRepo;

        $_SERVER['PHP_SELF'] = '/web/offers';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function builder(): OfferVoteListBuilder
    {
        return new OfferVoteListBuilder($this->offerRepo, $this->voteRepo);
    }

    public function test_rows_map_vote_enum_and_username(): void
    {
        $this->offerRepo->shouldReceive('getOfferName')->with(5)->andReturn('Cool & <offer>');
        $this->voteRepo->shouldReceive('getVoteCount')->with(5)->andReturn(2);
        $this->voteRepo->shouldReceive('getVoteRows')
            ->with(5, Mockery::type('int'), Mockery::type('int'))
            ->andReturn(collect([
                (object) ['userid' => 1, 'vote' => 0],
                (object) ['userid' => 1, 'vote' => 1],
                (object) ['userid' => 0, 'vote' => 99],
            ]));

        $r = $this->builder()->build(Request::create('/web/offers?id=5', 'GET'));

        $this->assertSame(5, $r['offerId']);
        $this->assertTrue($r['hasVotes']);
        $this->assertSame('Cool &amp; &lt;offer&gt;', $r['offerName']);
        $this->assertSame('yeah', $r['rows'][0]['vote']);
        $this->assertSame('against', $r['rows'][1]['vote']);
        $this->assertSame('unknown', $r['rows'][2]['vote']);
        $this->assertNotEmpty($r['pagerTop']);
    }

    public function test_empty_vote_list(): void
    {
        $this->offerRepo->shouldReceive('getOfferName')->with(7)->andReturn('X');
        $this->voteRepo->shouldReceive('getVoteCount')->with(7)->andReturn(0);
        $this->voteRepo->shouldReceive('getVoteRows')->andReturn(collect());

        $r = $this->builder()->build(Request::create('/web/offers?id=7', 'GET'));

        $this->assertFalse($r['hasVotes']);
        $this->assertSame([], $r['rows']);
        $this->assertSame((string) __('offers.std_no_votes_yet'), $r['noVotesNote']);
        $this->assertNotNull($r['pagerTop']);
    }
}
