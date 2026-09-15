<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\OfferVote;
use App\Models\User;
use App\Repositories\OfferVoteRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for OfferVoteRepository.
 *
 * Covers getVoteCounts(), getVoteCount(), getVoteRows(), userVoted(), recordVote(), incrementVote(), deleteOfferVotes().
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class OfferVoteRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private OfferVoteRepository $repository;

    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('offervotes')->delete();
        DB::table('offers')->delete();
        DB::table('comments')->where('offer', '>', 0)->delete();
        DB::table('staffmessages')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->repository = new OfferVoteRepository;

        /** @var User $user */
        $user = User::factory()->create();
        $this->userId = $user->id;
    }

    public function test_get_vote_counts_returns_zero_when_no_votes(): void
    {
        $id = $this->insertOffer('No Votes');

        $counts = $this->repository->getVoteCounts($id);

        $this->assertSame(['yeah' => 0, 'against' => 0], $counts);
    }

    public function test_get_vote_counts_returns_counts_by_type(): void
    {
        $id = $this->insertOffer('With Votes');

        $this->repository->recordVote($id, $this->userId, 'yeah');
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $this->repository->recordVote($id, $user2->id, 'against');

        $counts = $this->repository->getVoteCounts($id);

        $this->assertSame(1, $counts['yeah']);
        $this->assertSame(1, $counts['against']);
    }

    public function test_get_vote_count_returns_zero_when_no_votes(): void
    {
        $id = $this->insertOffer('No Votes Count');

        $this->assertSame(0, $this->repository->getVoteCount($id));
    }

    public function test_get_vote_count_returns_total_count(): void
    {
        $id = $this->insertOffer('Vote Count Offer');
        $this->repository->recordVote($id, $this->userId, 'yeah');

        $this->assertSame(1, $this->repository->getVoteCount($id));
    }

    public function test_get_vote_rows_returns_paginated_rows_ordered_by_id(): void
    {
        $id = $this->insertOffer('Vote Rows Offer');
        /** @var User $user2 */
        $user2 = User::factory()->create();

        $this->repository->recordVote($id, $this->userId, 'yeah');
        $this->repository->recordVote($id, $user2->id, 'against');

        $rows = $this->repository->getVoteRows($id, 0, 10)->all();

        $this->assertCount(2, $rows);
        $this->assertSame($this->userId, (int) $rows[0]->userid);
        $this->assertSame($user2->id, (int) $rows[1]->userid);
    }

    public function test_get_vote_rows_respects_offset_and_limit(): void
    {
        $id = $this->insertOffer('Paginated Votes');
        /** @var User $user2 */
        $user2 = User::factory()->create();

        $this->repository->recordVote($id, $this->userId, 'yeah');
        $this->repository->recordVote($id, $user2->id, 'against');

        $rows = $this->repository->getVoteRows($id, 1, 10)->all();

        $this->assertCount(1, $rows);
        $this->assertSame($user2->id, (int) $rows[0]->userid);
    }

    public function test_user_voted_returns_false_when_not_voted(): void
    {
        $id = $this->insertOffer('Vote Check');

        $this->assertFalse($this->repository->userVoted($id, $this->userId));
    }

    public function test_user_voted_returns_true_when_voted(): void
    {
        $id = $this->insertOffer('Vote Check Yes');
        $this->repository->recordVote($id, $this->userId, 'yeah');

        $this->assertTrue($this->repository->userVoted($id, $this->userId));
    }

    public function test_record_vote_inserts_row(): void
    {
        $id = $this->insertOffer('Record Vote');

        $this->repository->recordVote($id, $this->userId, 'against');

        $this->assertSame(1, DB::table('offervotes')->where('offerid', $id)->where('userid', $this->userId)->count());
        $this->assertSame(OfferVote::AGAINST->value, (int) DB::table('offervotes')->where('offerid', $id)->value('vote'));
    }

    public function test_increment_vote_increments_yeah_column(): void
    {
        $id = $this->insertOffer('Increment Yeah');

        $result = $this->repository->incrementVote($id, 'yeah');

        $this->assertTrue($result);
        $this->assertSame(1, (int) DB::table('offers')->where('id', $id)->value('yeah'));
    }

    public function test_increment_vote_increments_against_column(): void
    {
        $id = $this->insertOffer('Increment Against');

        $result = $this->repository->incrementVote($id, 'against');

        $this->assertTrue($result);
        $this->assertSame(1, (int) DB::table('offers')->where('id', $id)->value('against'));
    }

    public function test_delete_offer_votes_removes_votes(): void
    {
        $id = $this->insertOffer('Delete Votes');
        $this->repository->recordVote($id, $this->userId, 'yeah');

        $count = $this->repository->deleteOfferVotes($id);

        $this->assertSame(1, $count);
        $this->assertSame(0, DB::table('offervotes')->where('offerid', $id)->count());
    }

    private function insertOffer(string $name, int $yeah = 0, int $against = 0, int $category = 0, ?int $userId = null): int
    {
        return (int) DB::table('offers')->insertGetId([
            'userid' => $userId ?? $this->userId,
            'name' => $name,
            'added' => now()->toDateTimeString(),
            'yeah' => $yeah,
            'against' => $against,
            'category' => $category,
            'comments' => 0,
            'allowed' => 1,
        ]);
    }

    private function ensureCategory(): int
    {
        return (int) DB::table('categories')->insertGetId([
            'mode' => 1,
            'class_name' => 'test',
            'name' => 'Test Category',
            'image' => 'test.gif',
            'sort_index' => 0,
        ]);
    }
}
