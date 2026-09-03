<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\UserSearchRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for UserSearchRepository.
 *
 * Covers administrativeSearch() with various filter combinations,
 * validation errors, pagination, and edge cases.
 */
final class UserSearchRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UserSearchRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->repository = new UserSearchRepository;
    }

    public function test_administrative_search_returns_empty_when_no_users(): void
    {
        $result = $this->repository->administrativeSearch([], false);

        $this->assertSame(0, $result['count']);
        $this->assertSame([], $result['rows']);
        $this->assertSame('', $result['q']);
    }

    public function test_administrative_search_returns_all_users_with_no_filters(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $result = $this->repository->administrativeSearch([], false);

        $this->assertSame(1, $result['count']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame($user->id, (int) $result['rows'][0]['id']);
    }

    public function test_administrative_search_filters_by_username(): void
    {
        /** @var User $user1 */
        $user1 = User::factory()->create(['username' => 'alpha_user']);
        /** @var User $user2 */
        $user2 = User::factory()->create(['username' => 'beta_user']);

        $result = $this->repository->administrativeSearch(['n' => 'alpha_user'], false);

        $this->assertSame(1, $result['count']);
        $this->assertSame($user1->id, (int) $result['rows'][0]['id']);
    }

    public function test_administrative_search_filters_by_username_wildcard(): void
    {
        User::factory()->create(['username' => 'alpha_user']);
        User::factory()->create(['username' => 'beta_user']);
        User::factory()->create(['username' => 'alpha_admin']);

        $result = $this->repository->administrativeSearch(['n' => 'alpha*'], false);

        $this->assertSame(2, $result['count']);
    }

    public function test_administrative_search_excludes_username_with_tilde(): void
    {
        User::factory()->create(['username' => 'alpha_user']);
        User::factory()->create(['username' => 'beta_user']);

        $result = $this->repository->administrativeSearch(['n' => '~alpha_user'], false);

        $this->assertSame(1, $result['count']);
        $this->assertSame('beta_user', $result['rows'][0]['username']);
    }

    public function test_administrative_search_filters_by_email(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['email' => 'special@example.com']);
        User::factory()->create(['email' => 'other@example.com']);

        $result = $this->repository->administrativeSearch(['em' => 'special@example.com'], false);

        $this->assertSame(1, $result['count']);
        $this->assertSame($user->id, (int) $result['rows'][0]['id']);
    }

    public function test_administrative_search_throws_for_bad_email(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad email.');

        $this->repository->administrativeSearch(['em' => 'not-an-email'], false);
    }

    public function test_administrative_search_filters_by_class(): void
    {
        User::factory()->class(1)->create();
        /** @var User $user */
        $user = User::factory()->class(3)->create();

        // class param is 1-indexed offset by 2, so c=5 => class=3
        $result = $this->repository->administrativeSearch(['c' => 5], false);

        $this->assertSame(1, $result['count']);
        $this->assertSame($user->id, (int) $result['rows'][0]['id']);
    }

    public function test_administrative_search_throws_for_bad_ip(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad IP.');

        $this->repository->administrativeSearch(['ip' => 'not-an-ip'], false);
    }

    public function test_administrative_search_filters_by_ip(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['ip' => '192.168.1.100']);
        User::factory()->create(['ip' => '10.0.0.1']);

        $result = $this->repository->administrativeSearch(['ip' => '192.168.1.100'], false);

        $this->assertSame(1, $result['count']);
        $this->assertSame($user->id, (int) $result['rows'][0]['id']);
    }

    public function test_administrative_search_filters_by_ip_with_cidr_mask(): void
    {
        User::factory()->create(['ip' => '192.168.1.50']);
        User::factory()->create(['ip' => '10.0.0.1']);

        $result = $this->repository->administrativeSearch(['ip' => '192.168.1.50', 'ma' => '/24'], false);

        $this->assertSame(1, $result['count']);
    }

    public function test_administrative_search_throws_for_bad_cidr(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad subnet mask.');

        $this->repository->administrativeSearch(['ip' => '192.168.1.1', 'ma' => '/99'], false);
    }

    public function test_administrative_search_filters_by_ratio_no_data(): void
    {
        User::factory()->create(['uploaded' => 0, 'downloaded' => 0]);
        User::factory()->create(['uploaded' => 1000, 'downloaded' => 500]);

        $result = $this->repository->administrativeSearch(['r' => '---'], false);

        $this->assertSame(1, $result['count']);
    }

    public function test_administrative_search_filters_by_ratio_infinite(): void
    {
        User::factory()->create(['uploaded' => 1000, 'downloaded' => 0]);
        User::factory()->create(['uploaded' => 500, 'downloaded' => 500]);

        $result = $this->repository->administrativeSearch(['r' => 'inf'], false);

        $this->assertSame(1, $result['count']);
    }

    public function test_administrative_search_throws_for_bad_ratio(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad ratio.');

        $this->repository->administrativeSearch(['r' => '-1'], false);
    }

    public function test_administrative_search_filters_by_ratio_greater_than(): void
    {
        User::factory()->create(['uploaded' => 2000, 'downloaded' => 1000]);
        User::factory()->create(['uploaded' => 500, 'downloaded' => 1000]);

        $result = $this->repository->administrativeSearch(['r' => '1.5', 'rt' => '1'], false);

        $this->assertSame(1, $result['count']);
    }

    public function test_administrative_search_filters_by_ratio_less_than(): void
    {
        User::factory()->create(['uploaded' => 2000, 'downloaded' => 1000]);
        User::factory()->create(['uploaded' => 500, 'downloaded' => 1000]);

        $result = $this->repository->administrativeSearch(['r' => '1.5', 'rt' => '2'], false);

        $this->assertSame(1, $result['count']);
    }

    public function test_administrative_search_filters_by_ratio_between(): void
    {
        User::factory()->create(['uploaded' => 1500, 'downloaded' => 1000]);
        User::factory()->create(['uploaded' => 5000, 'downloaded' => 1000]);
        User::factory()->create(['uploaded' => 100, 'downloaded' => 1000]);

        $result = $this->repository->administrativeSearch(['r' => '1.0', 'r2' => '2.0', 'rt' => '3'], false);

        $this->assertSame(1, $result['count']);
    }

    public function test_administrative_search_ratio_between_throws_without_second_ratio(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Two ratios needed for this type of search.');

        $this->repository->administrativeSearch(['r' => '1.0', 'rt' => '3'], false);
    }

    public function test_administrative_search_filters_by_uploaded_greater_than(): void
    {
        $unit = 1073741824;
        User::factory()->create(['uploaded' => 5 * $unit]);
        User::factory()->create(['uploaded' => 1 * $unit]);

        $result = $this->repository->administrativeSearch(['ul' => '3', 'ult' => '1'], false);

        $this->assertSame(1, $result['count']);
    }

    public function test_administrative_search_filters_by_downloaded_less_than(): void
    {
        $unit = 1073741824;
        User::factory()->create(['downloaded' => 5 * $unit]);
        User::factory()->create(['downloaded' => 1 * $unit]);

        $result = $this->repository->administrativeSearch(['dl' => '3', 'dlt' => '2'], false);

        $this->assertSame(1, $result['count']);
    }

    public function test_administrative_search_throws_for_bad_uploaded(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad uploaded amount.');

        $this->repository->administrativeSearch(['ul' => '-1'], false);
    }

    public function test_administrative_search_filters_by_status_confirmed(): void
    {
        User::factory()->create(['status' => 'confirmed']);
        User::factory()->create(['status' => 'pending']);

        $result = $this->repository->administrativeSearch(['st' => '1'], false);

        $this->assertSame(1, $result['count']);
    }

    public function test_administrative_search_filters_by_account_status_enabled(): void
    {
        User::factory()->create(['enabled' => 1]);
        User::factory()->disabled()->create();

        $result = $this->repository->administrativeSearch(['as' => '1'], false);

        $this->assertSame(1, $result['count']);
    }

    public function test_administrative_search_filters_by_donor(): void
    {
        User::factory()->create(['donor' => true]);
        User::factory()->create(['donor' => false]);

        $result = $this->repository->administrativeSearch(['do' => '1'], false);

        $this->assertSame(1, $result['count']);
    }

    public function test_administrative_search_filters_by_warned(): void
    {
        User::factory()->create(['warned' => true]);
        User::factory()->create(['warned' => false]);

        $result = $this->repository->administrativeSearch(['w' => '1'], false);

        $this->assertSame(1, $result['count']);
    }

    public function test_administrative_search_paginates_results(): void
    {
        for ($i = 0; $i < 5; $i++) {
            User::factory()->create();
        }

        $result = $this->repository->administrativeSearch(['page' => 0], false, 2);

        $this->assertSame(5, $result['count']);
        $this->assertCount(2, $result['rows']);
    }

    public function test_administrative_search_page_offset(): void
    {
        for ($i = 0; $i < 5; $i++) {
            User::factory()->create();
        }

        $page0 = $this->repository->administrativeSearch(['page' => 0], false, 2);
        $page1 = $this->repository->administrativeSearch(['page' => 1], false, 2);

        $this->assertCount(2, $page0['rows']);
        $this->assertCount(2, $page1['rows']);
        $this->assertNotSame($page0['rows'][0]['id'], $page1['rows'][0]['id']);
    }

    public function test_administrative_search_excludes_modcomment_column_when_disabled(): void
    {
        User::factory()->create();

        $result = $this->repository->administrativeSearch([], false);

        $this->assertArrayNotHasKey('modcomment', $result['rows'][0]);
    }

    public function test_administrative_search_h_param_skips_filters(): void
    {
        User::factory()->create(['username' => 'alpha']);
        User::factory()->create(['username' => 'beta']);

        $result = $this->repository->administrativeSearch(['h' => '1', 'n' => 'alpha'], false);

        $this->assertSame(2, $result['count']);
    }

    public function test_administrative_search_builds_query_string(): void
    {
        User::factory()->create(['username' => 'alpha']);

        $result = $this->repository->administrativeSearch(['n' => 'alpha'], false);

        $this->assertStringContainsString('n=alpha', $result['q']);
    }
}
