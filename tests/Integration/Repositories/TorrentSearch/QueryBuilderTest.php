<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories\TorrentSearch;

use App\Models\User;
use App\Repositories\TorrentSearch\QueryBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression: the anonymous-or-own OR fragment built for author search
 * (search_area = 3) must be wrapped in parentheses. Without them, AND
 * precedence splits the conjunction — banned = 0, visible = 1 and
 * approval_status = 1 only bound to the own-anonymous branch, leaking
 * banned/dead/unapproved torrents into author-search results.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class QueryBuilderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    /**
     * @param  array<string, mixed>  $searchParams
     * @param  array<string, mixed>  $curuser
     * @param  list<string>  $whereothera
     * @return array<string, mixed>
     */
    private function build(array $searchParams, array $curuser, array $whereothera = ['visible = 1', 'banned = 0']): array
    {
        return (new QueryBuilder)->buildWhere(
            $searchParams,
            $curuser,
            ['category = 5'],
            [],
            $whereothera,
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            '',
            false,
            false,
            false,
            false,
            false,
            false,
            false,
            [5],
            0,
            0,
            'alice',
            'alice',
            'added',
        );
    }

    public function test_author_search_or_fragment_is_parenthesized_for_regular_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        $built = $this->build(
            ['search' => 'alice', 'search_area' => 3, 'search_mode' => 0],
            ['id' => $user->id, 'username' => $user->username, 'ip' => '1.2.3.4', 'notifs' => ''],
        );

        $where = $built['where'];

        $this->assertStringContainsString(
            '((users.username LIKE ? AND torrents.anonymous = 0) OR (users.username LIKE ? AND torrents.anonymous = 1 AND users.id = ?))',
            $where,
        );
        // The visibility constraints must sit outside the OR fragment so
        // they bind to the whole author-search clause, not one branch.
        $this->assertMatchesRegularExpression(
            '/\)\s+AND\s+.*banned = 0$/',
            $where,
        );
    }

    public function test_author_search_guest_fragment_is_plain_conjunction(): void
    {
        $built = $this->build(
            ['search' => 'alice', 'search_area' => 3, 'search_mode' => 0],
            ['id' => null, 'username' => '', 'ip' => '1.2.3.4', 'notifs' => ''],
        );

        $this->assertStringContainsString(
            'users.username LIKE ? AND torrents.anonymous = 0',
            $built['where'],
        );
        $this->assertStringNotContainsString(' OR ', $built['where']);
    }
}
