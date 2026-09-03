<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Offer;
use App\Models\User;
use App\Repositories\OfferRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for OfferRepository.
 *
 * Covers findOffer(), findOfferWithUser(), findOfferWithVotes(), offerNameExists(),
 * createOffer(), getVoteCounts(), getOfferOwner(), getOfferName(), getVoteCount(),
 * getVoteRows(), userVoted(), recordVote(), incrementVote(), allowOffer(),
 * denyOffer(), updateOffer(), deleteOffer(), deleteOfferVotes(),
 * deleteOfferComments(), getLastComment(), countComments(), getComments(),
 * addStaffMessage(), getUsername(), getLegacyList(), and list().
 */
final class OfferRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private OfferRepository $repository;

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

        $this->repository = new OfferRepository;

        /** @var User $user */
        $user = User::factory()->create();
        $this->userId = $user->id;
    }

    public function test_find_offer_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findOffer(999999));
    }

    public function test_find_offer_returns_model_when_found(): void
    {
        $id = $this->insertOffer('Test Offer');

        $offer = $this->repository->findOffer($id);

        $this->assertInstanceOf(Offer::class, $offer);
        $this->assertSame($id, $offer->id);
        $this->assertSame('Test Offer', $offer->name);
    }

    public function test_find_offer_with_user_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findOfferWithUser(999999));
    }

    public function test_find_offer_with_user_returns_model_when_found(): void
    {
        $id = $this->insertOffer('With User Offer');

        $offer = $this->repository->findOfferWithUser($id);

        $this->assertInstanceOf(Offer::class, $offer);
        $this->assertSame('With User Offer', $offer->name);
    }

    public function test_find_offer_with_votes_returns_selected_columns(): void
    {
        $id = $this->insertOffer('Votes Offer', 3, 2);

        $offer = $this->repository->findOfferWithVotes($id);

        $this->assertInstanceOf(Offer::class, $offer);
        $this->assertSame(3, (int) $offer->yeah);
        $this->assertSame(2, (int) $offer->against);
        $this->assertSame('pending', $offer->allowed);
    }

    public function test_offer_name_exists_returns_false_when_not_found(): void
    {
        $this->assertFalse($this->repository->offerNameExists('Nonexistent Offer'));
    }

    public function test_offer_name_exists_returns_true_when_found(): void
    {
        $this->insertOffer('Unique Offer Name');

        $this->assertTrue($this->repository->offerNameExists('Unique Offer Name'));
    }

    public function test_create_offer_returns_id(): void
    {
        $id = $this->repository->createOffer([
            'userid' => $this->userId,
            'name' => 'Created Offer',
            'added' => now()->toDateTimeString(),
        ]);

        $this->assertGreaterThan(0, $id);
        $this->assertSame('Created Offer', DB::table('offers')->where('id', $id)->value('name'));
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

    public function test_get_offer_owner_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getOfferOwner(999999));
    }

    public function test_get_offer_owner_returns_user_id_when_found(): void
    {
        $id = $this->insertOffer('Owner Offer');

        $this->assertSame($this->userId, $this->repository->getOfferOwner($id));
    }

    public function test_get_offer_name_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getOfferName(999999));
    }

    public function test_get_offer_name_returns_name_when_found(): void
    {
        $id = $this->insertOffer('Named Offer');

        $this->assertSame('Named Offer', $this->repository->getOfferName($id));
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
        $this->assertSame('against', DB::table('offervotes')->where('offerid', $id)->value('vote'));
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

    public function test_allow_offer_sets_allowed_and_allowedtime(): void
    {
        $id = $this->insertOffer('Allow Me');
        $time = '2025-01-01 00:00:00';

        $result = $this->repository->allowOffer($id, $time);

        $this->assertTrue($result);
        $this->assertSame('allowed', DB::table('offers')->where('id', $id)->value('allowed'));
        $this->assertSame($time, DB::table('offers')->where('id', $id)->value('allowedtime'));
    }

    public function test_deny_offer_sets_denied(): void
    {
        $id = $this->insertOffer('Deny Me');

        $result = $this->repository->denyOffer($id);

        $this->assertTrue($result);
        $this->assertSame('denied', DB::table('offers')->where('id', $id)->value('allowed'));
    }

    public function test_update_offer_modifies_columns(): void
    {
        $id = $this->insertOffer('Update Me');

        $result = $this->repository->updateOffer($id, ['name' => 'Updated Name']);

        $this->assertTrue($result);
        $this->assertSame('Updated Name', DB::table('offers')->where('id', $id)->value('name'));
    }

    public function test_delete_offer_removes_row(): void
    {
        $id = $this->insertOffer('Delete Me');

        $result = $this->repository->deleteOffer($id);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('offers')->where('id', $id)->count());
    }

    public function test_delete_offer_returns_false_when_not_found(): void
    {
        $this->assertFalse($this->repository->deleteOffer(999999));
    }

    public function test_delete_offer_votes_removes_votes(): void
    {
        $id = $this->insertOffer('Delete Votes');
        $this->repository->recordVote($id, $this->userId, 'yeah');

        $count = $this->repository->deleteOfferVotes($id);

        $this->assertSame(1, $count);
        $this->assertSame(0, DB::table('offervotes')->where('offerid', $id)->count());
    }

    public function test_delete_offer_comments_removes_comments(): void
    {
        $id = $this->insertOffer('Delete Comments');
        DB::table('comments')->insert([
            'user' => $this->userId,
            'offer' => $id,
            'text' => 'test comment',
            'added' => now()->toDateTimeString(),
        ]);

        $count = $this->repository->deleteOfferComments($id);

        $this->assertSame(1, $count);
        $this->assertSame(0, DB::table('comments')->where('offer', $id)->count());
    }

    public function test_get_last_comment_returns_null_when_no_comments(): void
    {
        $id = $this->insertOffer('No Comments');

        $this->assertNull($this->repository->getLastComment($id));
    }

    public function test_get_last_comment_returns_latest_comment(): void
    {
        $id = $this->insertOffer('With Comments');
        DB::table('comments')->insert([
            'user' => $this->userId,
            'offer' => $id,
            'text' => 'older comment',
            'added' => '2025-01-01 00:00:00',
        ]);
        DB::table('comments')->insert([
            'user' => $this->userId,
            'offer' => $id,
            'text' => 'newer comment',
            'added' => '2025-06-01 00:00:00',
        ]);

        $result = $this->repository->getLastComment($id);

        $this->assertNotNull($result);
        $this->assertSame('newer comment', $result['text']);
    }

    public function test_count_comments_returns_zero_when_none(): void
    {
        $id = $this->insertOffer('Count Zero');

        $this->assertSame(0, $this->repository->countComments($id));
    }

    public function test_count_comments_returns_count(): void
    {
        $id = $this->insertOffer('Count Comments');
        DB::table('comments')->insert([
            'user' => $this->userId,
            'offer' => $id,
            'text' => 'comment 1',
            'added' => now()->toDateTimeString(),
        ]);
        DB::table('comments')->insert([
            'user' => $this->userId,
            'offer' => $id,
            'text' => 'comment 2',
            'added' => now()->toDateTimeString(),
        ]);

        $this->assertSame(2, $this->repository->countComments($id));
    }

    public function test_get_comments_returns_paginated_collection(): void
    {
        $id = $this->insertOffer('Get Comments');
        DB::table('comments')->insert([
            'user' => $this->userId,
            'offer' => $id,
            'text' => 'comment 1',
            'added' => now()->toDateTimeString(),
        ]);

        $comments = $this->repository->getComments($id, 0, 10)->all();

        $this->assertCount(1, $comments);
        $this->assertSame('comment 1', $comments[0]->text);
    }

    public function test_add_staff_message_inserts_row(): void
    {
        $id = $this->insertOffer('Staff Msg Offer');

        $this->repository->addStaffMessage($this->userId, 'sender_name', 'Staff Msg Offer', $id);

        $this->assertSame(1, DB::table('staffmessages')->where('sender', $this->userId)->count());
    }

    public function test_get_username_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->getUsername(999999));
    }

    public function test_get_username_returns_name_when_found(): void
    {
        $name = DB::table('users')->where('id', $this->userId)->value('username');

        $this->assertSame($name, $this->repository->getUsername($this->userId));
    }

    public function test_get_legacy_list_returns_count_and_rows(): void
    {
        $categoryId = $this->ensureCategory();
        $id = $this->insertOffer('Legacy List Offer', 0, 0, $categoryId);

        $result = $this->repository->getLegacyList(0, 0, '', '', 'desc', 0, 10);

        $this->assertGreaterThan(0, $result['count']);
        $found = false;
        foreach ($result['rows'] as $row) {
            if ((int) $row->id === $id) {
                $found = true;
                $this->assertSame('Legacy List Offer', $row->name);
            }
        }
        $this->assertTrue($found);
    }

    public function test_get_legacy_list_filters_by_search(): void
    {
        $categoryId = $this->ensureCategory();
        $this->insertOffer('Searchable Offer', 0, 0, $categoryId);
        $this->insertOffer('Different Name', 0, 0, $categoryId);

        $result = $this->repository->getLegacyList(0, 0, 'Searchable', '', 'desc', 0, 10);
        $rows = $result['rows']->all();

        $this->assertSame(1, $result['count']);
        $this->assertSame('Searchable Offer', $rows[0]->name);
    }

    public function test_get_legacy_list_filters_by_category(): void
    {
        $cat1 = $this->ensureCategory();
        $cat2 = $this->ensureCategory();
        $this->insertOffer('Cat One', 0, 0, $cat1);
        $this->insertOffer('Cat Two', 0, 0, $cat2);

        $result = $this->repository->getLegacyList($cat1, 0, '', '', 'desc', 0, 10);
        $rows = $result['rows']->all();

        $this->assertSame(1, $result['count']);
        $this->assertSame('Cat One', $rows[0]->name);
    }

    public function test_get_legacy_list_filters_by_offeror(): void
    {
        $categoryId = $this->ensureCategory();
        $this->insertOffer('Offeror One', 0, 0, $categoryId);
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $this->insertOffer('Offeror Two', 0, 0, $categoryId, $user2->id);

        $result = $this->repository->getLegacyList(0, $user2->id, '', '', 'desc', 0, 10);
        $rows = $result['rows']->all();

        $this->assertSame(1, $result['count']);
        $this->assertSame('Offeror Two', $rows[0]->name);
    }

    public function test_list_returns_paginated_array(): void
    {
        $categoryId = $this->ensureCategory();
        $this->insertOffer('List Offer', 0, 0, $categoryId);

        $request = Request::create('/offers', 'GET', ['page' => 1]);

        $result = $this->repository->list($request);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertSame(1, $result['page']);
        $this->assertGreaterThan(0, $result['total']);
    }

    public function test_list_filters_by_search(): void
    {
        $categoryId = $this->ensureCategory();
        $this->insertOffer('List Searchable', 0, 0, $categoryId);
        $this->insertOffer('List Other', 0, 0, $categoryId);

        $request = Request::create('/offers', 'GET', ['search' => 'List Searchable']);

        $result = $this->repository->list($request);

        $this->assertSame(1, $result['total']);
        $this->assertSame('List Searchable', $result['data'][0]['name']);
    }

    public function test_list_caps_per_page_at_100(): void
    {
        $request = Request::create('/offers', 'GET', ['per_page' => 500]);

        $result = $this->repository->list($request);

        $this->assertSame(100, $result['per_page']);
    }

    public function test_list_includes_filters_array(): void
    {
        $categoryId = $this->ensureCategory();
        $request = Request::create('/offers', 'GET', ['category' => $categoryId, 'search' => 'test']);

        $result = $this->repository->list($request);

        $this->assertSame($categoryId, $result['filters']['category']);
        $this->assertSame('test', $result['filters']['search']);
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
            'allowed' => 'pending',
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
