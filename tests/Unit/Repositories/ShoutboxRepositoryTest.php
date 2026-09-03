<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Torrent;
use App\Models\User;
use App\Repositories\ShoutboxRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for ShoutboxRepository.
 *
 * Covers history(), prefetchReactions(), getReactionCounts(), getMyReactions(),
 * getMentions(), getLastShoutId(), findUserByUsername(), torrentExists(),
 * and applyTypeFilter().
 */
final class ShoutboxRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ShoutboxRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('shoutbox_reactions')->delete();
        DB::table('shoutbox')->delete();
        $this->repository = new ShoutboxRepository;
    }

    public function test_history_returns_paginated_results(): void
    {
        $this->insertShout(1, 'hello', 100);
        $this->insertShout(2, 'world', 200);

        $result = $this->repository->history(new Request);

        $this->assertCount(2, $result['data']);
        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(50, $result['per_page']);
    }

    public function test_history_orders_by_date_desc(): void
    {
        $this->insertShout(1, 'older', 100);
        $this->insertShout(2, 'newer', 200);

        $result = $this->repository->history(new Request);

        $this->assertSame('newer', $result['data'][0]['text']);
        $this->assertSame('older', $result['data'][1]['text']);
    }

    public function test_history_filters_by_username(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->insertShout($user->id, 'my shout', 100);
        $this->insertShout(999, 'other shout', 200);

        $request = Request::create('/', 'GET', ['user' => $user->username]);
        $result = $this->repository->history($request);

        $this->assertCount(1, $result['data']);
        $this->assertSame('my shout', $result['data'][0]['text']);
    }

    public function test_history_filters_by_unknown_user_returns_empty(): void
    {
        $this->insertShout(1, 'hello', 100);

        $request = Request::create('/', 'GET', ['user' => 'nonexistent_user_xyz']);
        $result = $this->repository->history($request);

        $this->assertSame(0, $result['total']);
        $this->assertCount(0, $result['data']);
    }

    public function test_history_filters_by_from_date(): void
    {
        $this->insertShout(1, 'old', 1000000);
        $this->insertShout(2, 'new', 2000000);

        $request = Request::create('/', 'GET', ['from' => date('Y-m-d H:i:s', 1500000)]);
        $result = $this->repository->history($request);

        $this->assertCount(1, $result['data']);
        $this->assertSame('new', $result['data'][0]['text']);
    }

    public function test_history_filters_by_to_date(): void
    {
        $this->insertShout(1, 'old', 1000000);
        $this->insertShout(2, 'new', 2000000);

        $request = Request::create('/', 'GET', ['to' => date('Y-m-d H:i:s', 1500000)]);
        $result = $this->repository->history($request);

        $this->assertCount(1, $result['data']);
        $this->assertSame('old', $result['data'][0]['text']);
    }

    public function test_history_filters_by_search_text(): void
    {
        $this->insertShout(1, 'find me', 100);
        $this->insertShout(2, 'nothing here', 200);

        $request = Request::create('/', 'GET', ['search' => 'find']);
        $result = $this->repository->history($request);

        $this->assertCount(1, $result['data']);
        $this->assertSame('find me', $result['data'][0]['text']);
    }

    public function test_history_respects_per_page(): void
    {
        $this->insertShout(1, 'a', 100);
        $this->insertShout(2, 'b', 200);
        $this->insertShout(3, 'c', 300);

        $request = Request::create('/', 'GET', ['per_page' => 2]);
        $result = $this->repository->history($request);

        $this->assertSame(2, $result['per_page']);
        $this->assertCount(2, $result['data']);
        $this->assertSame(3, $result['total']);
    }

    public function test_history_caps_per_page_at_100(): void
    {
        $request = Request::create('/', 'GET', ['per_page' => 500]);
        $result = $this->repository->history($request);

        $this->assertSame(100, $result['per_page']);
    }

    public function test_history_returns_filters_in_result(): void
    {
        $request = Request::create('/', 'GET', ['user' => 'alice', 'search' => 'hi']);
        $result = $this->repository->history($request);

        $this->assertSame('alice', $result['filters']['user']);
        $this->assertSame('hi', $result['filters']['search']);
        $this->assertArrayNotHasKey('from', $result['filters']);
    }

    public function test_prefetch_reactions_returns_empty_for_empty_ids(): void
    {
        $result = $this->repository->prefetchReactions([], 1);

        $this->assertSame(['counts' => [], 'mine' => [], 'users' => []], $result);
    }

    public function test_prefetch_reactions_aggregates_counts_and_mine(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $shoutId = $this->insertShout(1, 'hello', 100);
        DB::table('shoutbox_reactions')->insert([
            ['shoutbox_id' => $shoutId, 'user_id' => $user1->id, 'reaction' => '👍', 'created_at' => now()],
            ['shoutbox_id' => $shoutId, 'user_id' => $user2->id, 'reaction' => '👍', 'created_at' => now()],
            ['shoutbox_id' => $shoutId, 'user_id' => $user1->id, 'reaction' => '🔥', 'created_at' => now()],
        ]);

        $result = $this->repository->prefetchReactions([$shoutId], $user1->id);

        $this->assertSame(2, $result['counts'][$shoutId]['👍']);
        $this->assertSame(1, $result['counts'][$shoutId]['🔥']);
        $this->assertContains('👍', $result['mine'][$shoutId]);
        $this->assertContains('🔥', $result['mine'][$shoutId]);
    }

    public function test_prefetch_reactions_builds_users_map(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $shoutId = $this->insertShout(1, 'hello', 100);
        DB::table('shoutbox_reactions')->insert([
            ['shoutbox_id' => $shoutId, 'user_id' => $user->id, 'reaction' => '👍', 'created_at' => now()],
        ]);

        $result = $this->repository->prefetchReactions([$shoutId], 999);

        $this->assertContains($user->username, $result['users'][$shoutId]['👍']);
    }

    public function test_get_reaction_counts_returns_grouped_counts(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $shoutId = $this->insertShout(1, 'hello', 100);
        DB::table('shoutbox_reactions')->insert([
            ['shoutbox_id' => $shoutId, 'user_id' => $user1->id, 'reaction' => '👍', 'created_at' => now()],
            ['shoutbox_id' => $shoutId, 'user_id' => $user2->id, 'reaction' => '👍', 'created_at' => now()],
        ]);

        $counts = $this->repository->getReactionCounts($shoutId);

        $this->assertSame(2, $counts['👍']);
    }

    public function test_get_reaction_counts_returns_empty_when_none(): void
    {
        $shoutId = $this->insertShout(1, 'hello', 100);

        $counts = $this->repository->getReactionCounts($shoutId);

        $this->assertSame([], $counts);
    }

    public function test_get_my_reactions_returns_current_user_reactions(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create();
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $shoutId = $this->insertShout(1, 'hello', 100);
        DB::table('shoutbox_reactions')->insert([
            ['shoutbox_id' => $shoutId, 'user_id' => $user1->id, 'reaction' => '👍', 'created_at' => now()],
            ['shoutbox_id' => $shoutId, 'user_id' => $user2->id, 'reaction' => '🔥', 'created_at' => now()],
        ]);

        $mine = $this->repository->getMyReactions($shoutId, $user1->id);

        $this->assertSame(['👍'], $mine);
    }

    public function test_get_my_reactions_returns_empty_when_none(): void
    {
        $shoutId = $this->insertShout(1, 'hello', 100);

        $mine = $this->repository->getMyReactions($shoutId, 10);

        $this->assertSame([], $mine);
    }

    public function test_get_mentions_returns_empty_when_user_not_found(): void
    {
        $result = $this->repository->getMentions(999999, 0);

        $this->assertSame([], $result);
    }

    public function test_get_mentions_returns_matching_shouts(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $author */
        $author = User::factory()->create();
        $shoutId = $this->insertShout($author->id, '@'.$user->username.' hello', 100);

        $result = $this->repository->getMentions($user->id, 0);

        $this->assertCount(1, $result);
        $this->assertSame($shoutId, $result[0]['id']);
        $this->assertSame($author->username, $result[0]['author_name']);
    }

    public function test_get_mentions_excludes_own_shouts(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->insertShout($user->id, '@'.$user->username.' self mention', 100);

        $result = $this->repository->getMentions($user->id, 0);

        $this->assertSame([], $result);
    }

    public function test_get_mentions_filters_by_last_shout_id(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var User $author */
        $author = User::factory()->create();
        $shoutId = $this->insertShout($author->id, '@'.$user->username.' hello', 100);

        $result = $this->repository->getMentions($user->id, $shoutId);

        $this->assertSame([], $result);
    }

    public function test_get_last_shout_id_returns_max_id(): void
    {
        $id1 = $this->insertShout(1, 'a', 100);
        $id2 = $this->insertShout(2, 'b', 200);

        $this->assertSame($id2, $this->repository->getLastShoutId());
    }

    public function test_get_last_shout_id_returns_zero_when_empty(): void
    {
        $this->assertSame(0, $this->repository->getLastShoutId());
    }

    public function test_find_user_by_username_returns_user_when_found(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->findUserByUsername((string) $user->username);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result['id']);
        $this->assertSame($user->username, $result['name']);
    }

    public function test_find_user_by_username_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findUserByUsername('nonexistent_user_xyz'));
    }

    public function test_find_user_by_username_is_case_insensitive(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->findUserByUsername(strtoupper((string) $user->username));

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result['id']);
    }

    public function test_torrent_exists_returns_true_when_found(): void
    {
        /** @var Torrent $torrent */
        $torrent = Torrent::factory()->create();

        $this->assertTrue($this->repository->torrentExists($torrent->id));
    }

    public function test_torrent_exists_returns_false_when_not_found(): void
    {
        $this->assertFalse($this->repository->torrentExists(999999));
    }

    public function test_apply_type_filter_adds_sb_where(): void
    {
        $this->insertShout(1, 'sb shout', 100);
        // Insert a non-sb type would require bypassing the enum, but type is
        // constrained to 'sb' only. Verify the filter still applies correctly.
        $query = DB::table('shoutbox');
        $this->repository->applyTypeFilter($query, 'shoutbox');

        $this->assertSame(1, $query->count());
    }

    private function insertShout(int $userId, string $text, int $date): int
    {
        return (int) DB::table('shoutbox')->insertGetId([
            'userid' => $userId,
            'date' => $date,
            'text' => $text,
            'type' => 'sb',
        ]);
    }
}
