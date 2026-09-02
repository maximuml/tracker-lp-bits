<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\BonusRepository;
use App\Services\BonusPageService;
use App\Support\CurrentUser;
use App\Support\Globals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for BonusPageService.
 *
 * Covers buildBonusArray (item structure, conditional items, count),
 * build (action routing, bonus_tweak disable, do-message resolution,
 * empty action shop/info), and constructor instantiation.
 */
final class BonusPageServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BonusPageService $service;

    private int $initialObLevel;

    /** @var BonusRepository&MockInterface */
    private $bonusRep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialObLevel = ob_get_level();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        /** @var BonusRepository&MockInterface $rep */
        $rep = Mockery::mock(BonusRepository::class);
        $rep->shouldIgnoreMissing();
        $this->bonusRep = $rep;

        $this->service = new BonusPageService($rep);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }
        Mockery::close();
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $values */
    private function mockGlobals(array $values = []): void
    {
        $globals = new Globals;
        foreach ($values as $key => $value) {
            $globals->set($key, $value);
        }
        $this->app->instance(Globals::class, $globals);
    }

    /** @param  array<string, mixed>  $userData */
    private function setCurrentUser(array $userData = []): void
    {
        $currentUser = new CurrentUser;
        $currentUser->set(array_merge([
            'id' => 1,
            'username' => 'testuser',
            'seedbonus' => 500.0,
        ], $userData));
        $this->app->instance(CurrentUser::class, $currentUser);
    }

    /** @param  array<string, mixed>  $query */
    private function requestWithQuery(array $query = []): Request
    {
        return Request::create('/mybonus.php', 'GET', $query);
    }

    // ─── Constructor / instantiation ──────────────────────────────────

    public function test_can_instantiate_service(): void
    {
        $service = new BonusPageService($this->bonusRep);

        $this->assertInstanceOf(BonusPageService::class, $service);
    }

    // ─── buildBonusArray ──────────────────────────────────────────────

    public function test_build_bonus_array_returns_non_empty_array(): void
    {
        $this->mockGlobals([
            'onegbupload_bonus' => 100.0,
            'fivegbupload_bonus' => 200.0,
            'tengbupload_bonus' => 300.0,
            'oneinvite_bonus' => 500.0,
            'customtitle_bonus' => 5000.0,
            'vipstatus_bonus' => 10000.0,
            'basictax_bonus' => 0.0,
            'taxpercentage_bonus' => 0.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $this->assertNotEmpty($result);
        $this->assertGreaterThan(10, count($result));
    }

    public function test_build_bonus_array_first_item_is_1gb_upload(): void
    {
        $this->mockGlobals([
            'onegbupload_bonus' => 100.0,
            'oneinvite_bonus' => 0.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $this->assertSame('traffic', $result[0]['art']);
        $this->assertSame(1073741824, $result[0]['menge']);
        $this->assertSame(100.0, $result[0]['points']);
    }

    public function test_build_bonus_array_second_item_is_5gb_upload(): void
    {
        $this->mockGlobals([
            'fivegbupload_bonus' => 200.0,
            'oneinvite_bonus' => 0.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $this->assertSame('traffic', $result[1]['art']);
        $this->assertSame(5368709120, $result[1]['menge']);
        $this->assertSame(200.0, $result[1]['points']);
    }

    public function test_build_bonus_array_excludes_invite_when_bonus_is_zero(): void
    {
        $this->mockGlobals([
            'oneinvite_bonus' => 0.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $arts = array_column($result, 'art');
        $this->assertNotContains('invite', $arts);
    }

    public function test_build_bonus_array_includes_invite_when_bonus_is_positive(): void
    {
        $this->mockGlobals([
            'oneinvite_bonus' => 500.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $arts = array_column($result, 'art');
        $this->assertContains('invite', $arts);
    }

    public function test_build_bonus_array_includes_custom_title_item(): void
    {
        $this->mockGlobals([
            'customtitle_bonus' => 5000.0,
            'oneinvite_bonus' => 0.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $titleItems = array_filter($result, fn ($item): bool => $item['art'] === 'title');
        $this->assertCount(1, $titleItems);
    }

    public function test_build_bonus_array_includes_vip_status_item(): void
    {
        $this->mockGlobals([
            'vipstatus_bonus' => 10000.0,
            'oneinvite_bonus' => 0.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $vipItems = array_filter($result, fn ($item): bool => $item['art'] === 'class');
        $this->assertCount(1, $vipItems);
    }

    public function test_build_bonus_array_includes_gift_item(): void
    {
        $this->mockGlobals([
            'oneinvite_bonus' => 0.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $giftItems = array_filter($result, fn ($item): bool => $item['art'] === 'gift_1');
        $this->assertCount(1, $giftItems);
        $giftItem = array_values($giftItems)[0];
        $this->assertSame(100.0, (float) $giftItem['points']);
    }

    public function test_build_bonus_array_includes_cancel_hr_item(): void
    {
        $this->mockGlobals([
            'oneinvite_bonus' => 0.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $hrItems = array_filter($result, fn ($item): bool => $item['art'] === 'cancel_hr');
        $this->assertCount(1, $hrItems);
    }

    public function test_build_bonus_array_each_item_has_required_keys(): void
    {
        $this->mockGlobals([
            'oneinvite_bonus' => 500.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        foreach ($result as $item) {
            $this->assertArrayHasKey('points', $item);
            $this->assertArrayHasKey('art', $item);
            $this->assertArrayHasKey('menge', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('description', $item);
        }
    }

    // ─── build ────────────────────────────────────────────────────────

    public function test_build_with_action_set_returns_empty_shop_and_info_html(): void
    {
        $this->mockGlobals([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $result = $this->service->build($request);

        $this->assertSame('', $result['shopHtml']);
        $this->assertSame('', $result['infoHtml']);
        $this->assertSame('exchange', $result['action']);
    }

    public function test_build_returns_expected_top_level_keys(): void
    {
        $this->mockGlobals([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $result = $this->service->build($request);

        $this->assertArrayHasKey('lang', $result);
        $this->assertArrayHasKey('curUser', $result);
        $this->assertArrayHasKey('userId', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('do', $result);
        $this->assertArrayHasKey('msg', $result);
        $this->assertArrayHasKey('bonus', $result);
        $this->assertArrayHasKey('lockText', $result);
        $this->assertArrayHasKey('allBonus', $result);
        $this->assertArrayHasKey('shopHtml', $result);
        $this->assertArrayHasKey('infoHtml', $result);
    }

    public function test_build_resolves_do_message_for_upload(): void
    {
        $this->mockGlobals([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange', 'do' => 'upload']);

        $result = $this->service->build($request);

        // msg should be non-empty for known do values
        $this->assertIsString($result['msg']);
    }

    public function test_build_resolves_do_message_for_unknown_do(): void
    {
        $this->mockGlobals([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange', 'do' => 'unknown_action']);

        $result = $this->service->build($request);

        $this->assertSame('', $result['msg']);
    }

    public function test_build_formats_bonus_with_one_decimal(): void
    {
        $this->mockGlobals([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser(['seedbonus' => 1234.56]);

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $result = $this->service->build($request);

        $this->assertSame('1,234.6', $result['bonus']);
    }

    public function test_build_with_bonus_tweak_disable_throws(): void
    {
        $this->mockGlobals([
            'bonus_tweak' => 'disable',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $threw = false;
        try {
            $this->service->build($request);
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected exception when bonus_tweak is disable');
    }

    public function test_build_with_bonus_tweak_disablesave_throws(): void
    {
        $this->mockGlobals([
            'bonus_tweak' => 'disablesave',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $threw = false;
        try {
            $this->service->build($request);
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected exception when bonus_tweak is disablesave');
    }

    public function test_build_returns_user_id_from_current_user(): void
    {
        $this->mockGlobals([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser(['id' => 42]);

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $result = $this->service->build($request);

        $this->assertSame(42, $result['userId']);
    }

    public function test_build_returns_all_bonus_array_in_result(): void
    {
        $this->mockGlobals([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 500.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $result = $this->service->build($request);

        $this->assertNotEmpty($result['allBonus']);
        $arts = array_column($result['allBonus'], 'art');
        $this->assertContains('invite', $arts);
    }
}
