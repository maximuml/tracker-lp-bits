<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Torrent;
use App\Models\User;
use App\Repositories\ToptenRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for ToptenRepository.
 *
 * Covers page() with all type values (1=user, 2=torrent, 3=country,
 * 5=community, 6=other), limit clamping, and invalid type fallback.
 */
final class ToptenRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private ToptenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('peers')->delete();
        DB::table('torrents')->delete();
        DB::table('users')->delete();
        DB::table('countries')->delete();
        DB::table('topics')->delete();
        DB::table('posts')->delete();
        DB::table('comments')->delete();
        DB::table('forums')->delete();
        DB::table('agent_allowed_family')->delete();
        DB::table('stylesheets')->delete();
        DB::table('language')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->repository = new ToptenRepository;
    }

    public function test_page_returns_user_sections_for_type_1(): void
    {
        User::factory()->create(['uploaded' => 1000, 'downloaded' => 500, 'enabled' => 1]);

        $result = $this->repository->page(1, 10, null);

        $this->assertSame(1, $result['type']);
        $this->assertSame(10, $result['limit']);
        $this->assertIsArray($result['sections']);
        $this->assertNotEmpty($result['sections']);
    }

    public function test_page_returns_torrent_sections_for_type_2(): void
    {
        Torrent::factory()->create();

        $result = $this->repository->page(2, 10, null);

        $this->assertSame(2, $result['type']);
        $this->assertNotEmpty($result['sections']);
    }

    public function test_page_returns_country_sections_for_type_3(): void
    {
        $countryId = (int) DB::table('countries')->insertGetId([
            'name' => 'TestLand',
            'flagpic' => 'test.png',
        ]);
        User::factory()->create(['country' => $countryId, 'enabled' => 1]);

        $result = $this->repository->page(3, 10, null);

        $this->assertSame(3, $result['type']);
        $this->assertNotEmpty($result['sections']);
    }

    public function test_page_returns_community_sections_for_type_5(): void
    {
        User::factory()->create();

        $result = $this->repository->page(5, 10, null);

        $this->assertSame(5, $result['type']);
        $this->assertNotEmpty($result['sections']);
    }

    public function test_page_returns_other_sections_for_type_6(): void
    {
        User::factory()->create(['seedbonus' => 100.0]);

        $result = $this->repository->page(6, 10, null);

        $this->assertSame(6, $result['type']);
        $this->assertNotEmpty($result['sections']);
    }

    public function test_page_falls_back_to_type_1_for_invalid_type(): void
    {
        $result = $this->repository->page(999, 10, null);

        $this->assertSame(1, $result['type']);
    }

    public function test_page_clamps_limit_to_10_when_below_1(): void
    {
        $result = $this->repository->page(1, 0, null);

        $this->assertSame(10, $result['limit']);
    }

    public function test_page_clamps_limit_to_10_when_above_250(): void
    {
        $result = $this->repository->page(1, 500, null);

        $this->assertSame(10, $result['limit']);
    }

    public function test_page_respects_valid_limit(): void
    {
        $result = $this->repository->page(1, 100, null);

        $this->assertSame(100, $result['limit']);
    }

    public function test_page_user_sections_filter_by_subtype(): void
    {
        User::factory()->create(['uploaded' => 5000, 'downloaded' => 1000, 'enabled' => 1]);

        $result = $this->repository->page(1, 100, 'ul');

        $this->assertCount(1, $result['sections']);
        $this->assertSame('ul', $result['sections'][0]['subtype']);
    }

    public function test_page_user_sections_returns_all_when_limit_10_and_no_subtype(): void
    {
        User::factory()->create(['uploaded' => 5000, 'downloaded' => 1000, 'enabled' => 1]);

        $result = $this->repository->page(1, 10, null);

        // When limit is 10 and no subtype, all user sections are returned
        $this->assertGreaterThan(1, count($result['sections']));
    }

    public function test_page_user_sections_returns_empty_when_limit_not_10_and_no_subtype(): void
    {
        User::factory()->create(['uploaded' => 5000, 'downloaded' => 1000, 'enabled' => 1]);

        $result = $this->repository->page(1, 100, null);

        // When limit is not 10 and no subtype, no sections are returned
        $this->assertCount(0, $result['sections']);
    }

    public function test_page_returns_enabled_donation_flag(): void
    {
        $result = $this->repository->page(6, 10, null);

        $this->assertIsBool($result['enabledDonation']);
    }

    public function test_page_returns_date_founded(): void
    {
        $result = $this->repository->page(1, 10, null);

        $this->assertIsString($result['dateFounded']);
    }

    public function test_page_returns_lang_array(): void
    {
        $result = $this->repository->page(1, 10, null);

        $this->assertIsArray($result['lang']);
    }

    public function test_page_torrent_sections_filter_by_subtype_act(): void
    {
        Torrent::factory()->create(['seeders' => 5, 'leechers' => 3]);

        $result = $this->repository->page(2, 100, 'act');

        $this->assertCount(1, $result['sections']);
        $this->assertSame('act', $result['sections'][0]['subtype']);
    }

    public function test_page_other_sections_includes_bonus_when_subtype_bo(): void
    {
        User::factory()->create(['seedbonus' => 500.0]);

        $result = $this->repository->page(6, 100, 'bo');

        $this->assertCount(1, $result['sections']);
        $this->assertSame('bo', $result['sections'][0]['subtype']);
    }
}
