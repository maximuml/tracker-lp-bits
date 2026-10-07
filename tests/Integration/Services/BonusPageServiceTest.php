<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Repositories\BonusCalculationRepository;
use App\Services\BonusPageService;
use App\Support\CurrentUser;
use App\Support\Strings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\Concerns\SeedsLegacySettings;
use Tests\TestCase;

/**
 * Unit tests for BonusPageService.
 *
 * Covers buildBonusArray (item structure, conditional items, count),
 * build (action routing, bonus_tweak disable, do-message resolution,
 * empty action shop/info), and constructor instantiation.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class BonusPageServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsLegacySettings;

    private BonusPageService $service;

    private int $initialObLevel;

    /** @var BonusCalculationRepository&MockInterface */
    private $bonusCalcRep;

    private CurrentUser $currentUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialObLevel = ob_get_level();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('users')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        /** @var BonusCalculationRepository&MockInterface $rep */
        $rep = Mockery::mock(BonusCalculationRepository::class);
        $rep->shouldIgnoreMissing();
        $this->bonusCalcRep = $rep;

        $this->currentUser = new CurrentUser;
        $this->app->instance(CurrentUser::class, $this->currentUser);

        $this->service = new BonusPageService($this->currentUser, $rep);
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
    private function seedSettings(array $values = []): void
    {
        $this->seedTestSettings($values);
    }

    /** @param  array<string, mixed>  $userData */
    private function setCurrentUser(array $userData = []): void
    {
        $this->currentUser->set(array_merge([
            'id' => 1,
            'username' => 'testuser',
            'seedbonus' => 500.0,
        ], $userData));
    }

    /** @param  array<string, mixed>  $query */
    private function requestWithQuery(array $query = []): Request
    {
        return Request::create('/mybonus.php', 'GET', $query);
    }

    // ─── Constructor / instantiation ──────────────────────────────────

    public function test_can_instantiate_service(): void
    {
        $service = new BonusPageService($this->currentUser, $this->bonusCalcRep);

        $this->assertInstanceOf(BonusPageService::class, $service);
    }

    // ─── buildBonusArray ──────────────────────────────────────────────

    public function test_build_bonus_array_returns_non_empty_array(): void
    {
        $this->seedSettings([
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
        $this->seedSettings([
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
        $this->seedSettings([
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
        $this->seedSettings([
            'oneinvite_bonus' => 0.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $arts = array_column($result, 'art');
        $this->assertNotContains('invite', $arts);
    }

    public function test_build_bonus_array_includes_invite_when_bonus_is_positive(): void
    {
        $this->seedSettings([
            'oneinvite_bonus' => 500.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $arts = array_column($result, 'art');
        $this->assertContains('invite', $arts);
    }

    public function test_build_bonus_array_includes_custom_title_item(): void
    {
        $this->seedSettings([
            'customtitle_bonus' => 5000.0,
            'oneinvite_bonus' => 0.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $titleItems = array_filter($result, fn ($item): bool => $item['art'] === 'title');
        $this->assertCount(1, $titleItems);
    }

    public function test_build_bonus_array_includes_vip_status_item(): void
    {
        $this->seedSettings([
            'vipstatus_bonus' => 10000.0,
            'oneinvite_bonus' => 0.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $vipItems = array_filter($result, fn ($item): bool => $item['art'] === 'class');
        $this->assertCount(1, $vipItems);
    }

    public function test_build_bonus_array_includes_gift_item(): void
    {
        $this->seedSettings([
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
        $this->seedSettings([
            'oneinvite_bonus' => 0.0,
        ]);

        $result = $this->service->buildBonusArray([]);

        $hrItems = array_filter($result, fn ($item): bool => $item['art'] === 'cancel_hr');
        $this->assertCount(1, $hrItems);
    }

    public function test_build_bonus_array_each_item_has_required_keys(): void
    {
        $this->seedSettings([
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

    public function test_build_with_action_set_returns_null_shop_and_info(): void
    {
        $this->seedSettings([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $result = $this->service->build($request)->toArray();

        $this->assertNull($result['shop']);
        $this->assertNull($result['info']);
        $this->assertSame('exchange', (string) ($result['action']));
    }

    public function test_build_returns_expected_top_level_keys(): void
    {
        $this->seedSettings([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $result = $this->service->build($request)->toArray();
        $this->assertArrayHasKey('curUser', $result);
        $this->assertArrayHasKey('userId', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('do', $result);
        $this->assertArrayHasKey('msg', $result);
        $this->assertArrayHasKey('bonus', $result);
        $this->assertArrayHasKey('lockText', $result);
        $this->assertArrayHasKey('allBonus', $result);
        $this->assertArrayHasKey('shop', $result);
        $this->assertArrayHasKey('info', $result);
    }

    public function test_build_resolves_do_message_for_upload(): void
    {
        $this->seedSettings([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange', 'do' => 'upload']);

        $result = $this->service->build($request)->toArray();

        // msg should be non-empty for known do values
        $this->assertIsString($result['msg']);
    }

    public function test_build_resolves_do_message_for_unknown_do(): void
    {
        $this->seedSettings([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange', 'do' => 'unknown_action']);

        $result = $this->service->build($request)->toArray();

        $this->assertSame('', (string) ($result['msg']));
    }

    public function test_build_formats_bonus_with_one_decimal(): void
    {
        $this->seedSettings([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser(['seedbonus' => 1234.56]);

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $result = $this->service->build($request)->toArray();

        $this->assertSame('1,234.6', (string) ($result['bonus']));
    }

    public function test_build_with_bonus_tweak_disable_throws(): void
    {
        $this->seedSettings([
            'bonus_tweak' => 'disable',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $threw = false;
        try {
            $this->service->build($request)->toArray();
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected exception when bonus_tweak is disable');
    }

    public function test_build_with_bonus_tweak_disablesave_throws(): void
    {
        $this->seedSettings([
            'bonus_tweak' => 'disablesave',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $threw = false;
        try {
            $this->service->build($request)->toArray();
        } catch (\Throwable) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Expected exception when bonus_tweak is disablesave');
    }

    public function test_build_returns_user_id_from_current_user(): void
    {
        $this->seedSettings([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser(['id' => 42]);

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $result = $this->service->build($request)->toArray();

        $this->assertSame(42, $result['userId']);
    }

    public function test_build_returns_all_bonus_array_in_result(): void
    {
        $this->seedSettings([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 500.0,
        ]);
        $this->setCurrentUser();

        $request = $this->requestWithQuery(['action' => 'exchange']);

        $result = $this->service->build($request)->toArray();

        $this->assertNotEmpty($result['allBonus']);
        $arts = array_column($result['allBonus'], 'art');
        $this->assertContains('invite', $arts);
    }

    private function assertAbortContains(callable $fn, string ...$needles): void
    {
        try {
            $fn();
            $this->fail('Expected abort');
        } catch (HttpResponseException $e) {
            $html = (string) $e->getResponse()->getContent();
            foreach ($needles as $needle) {
                $this->assertStringContainsString(e($needle), $html);
            }
        } catch (\Throwable $e) {
            $this->fail('Expected HttpResponseException, got '.$e::class);
        }
    }

    public function test_build_aborts_with_disabled_message_when_bonus_disabled(): void
    {
        $this->seedSettings([
            'bonus_tweak' => 'disable',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $this->assertAbortContains(
            fn () => $this->service->build($this->requestWithQuery(['action' => 'exchange'])),
            (string) __('mybonus.std_karma_system_disabled'),
        );
    }

    public function test_build_aborts_with_points_active_message_when_save_disabled(): void
    {
        $this->seedSettings([
            'bonus_tweak' => 'disablesave',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $this->assertAbortContains(
            fn () => $this->service->build($this->requestWithQuery(['action' => 'exchange'])),
            (string) __('mybonus.std_karma_system_disabled'),
            (string) __('mybonus.std_points_active'),
        );
    }

    public function test_build_all_bonus_pins_transfer_sizes_and_arts_in_order(): void
    {
        $this->seedSettings([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 500.0,
            'basictax_bonus' => 0.0,
            'taxpercentage_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $result = $this->service->build($this->requestWithQuery(['action' => 'exchange']))->toArray();

        $expected = [
            ['traffic', 1073741824],
            ['traffic', 5368709120],
            ['traffic', 10737418240],
            ['traffic', 107374182400],
            ['traffic_downloaded', 10737418240],
            ['traffic_downloaded', 107374182400],
            ['invite', 1],
            ['tmp_invite', 1],
            ['title', 0],
            ['class', 0],
            ['gift_1', 0],
            ['attendance_card', 0],
            ['rainbow_id', 0],
            ['change_username_card', 0],
            ['gift_2', 0],
            ['cancel_hr', 0],
        ];
        $this->assertSame($expected, array_map(
            static fn (array $item): array => [$item['art'], $item['menge']],
            $result['allBonus'],
        ));

        $byArt = collect($result['allBonus'])->keyBy('art');
        $this->assertSame(100.0, (float) $byArt->get('gift_1')['points']);
        $this->assertSame(1000.0, (float) $byArt->get('gift_2')['points']);
    }

    public function test_build_downloaded_item_names_contain_size_labels(): void
    {
        $this->seedSettings([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser();

        $result = $this->service->build($this->requestWithQuery(['action' => 'exchange']))->toArray();

        $downloaded = collect($result['allBonus'])->where('art', 'traffic_downloaded')->values();
        $this->assertCount(2, $downloaded);
        $this->assertStringContainsString(
            (string) __('mybonus.text_downloaded_ten_gb'),
            (string) $downloaded[0]['name']->render(),
        );
        $this->assertStringContainsString(
            (string) __('mybonus.text_downloaded_hundred_gb'),
            (string) $downloaded[1]['name']->render(),
        );
    }

    public function test_build_gift_tax_composes_amounts_and_rest(): void
    {
        $this->seedSettings([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
            'basictax_bonus' => 2.0,
            'taxpercentage_bonus' => 5.0,
        ]);
        $this->setCurrentUser();

        $result = $this->service->build($this->requestWithQuery(['action' => 'exchange']))->toArray();

        $gift = collect($result['allBonus'])->firstWhere('art', 'gift_1');
        $this->assertNotNull($gift);
        $giftTax = $gift['giftTax'];
        $this->assertArrayHasKey('charges', $giftTax);
        $this->assertArrayHasKey('amounts', $giftTax);
        $this->assertArrayHasKey('rest', $giftTax);
        $this->assertSame(
            '2'.(__('mybonus.text_tax_bonus_point')).Strings::addS(2.0).(__('mybonus.text_tax_plus'))
                .'5'.(__('mybonus.text_percent_of_transfered_amount')),
            (string) $giftTax['amounts'],
        );
        $this->assertSame(
            (__('mybonus.text_as_tax')).'93'.(__('mybonus.text_tax_example_note')),
            (string) $giftTax['rest'],
        );
    }

    public function test_build_resolves_all_do_message_arms(): void
    {
        $this->seedSettings([
            'bonus_tweak' => '',
            'oneinvite_bonus' => 0.0,
        ]);
        $this->setCurrentUser(['title' => 'My Title']);

        $lockText = sprintf((string) __('mybonus.lock_text'), 10);
        $expect = [
            'upload' => (string) __('mybonus.text_success_upload'),
            'download' => (string) __('mybonus.text_success_download'),
            'invite' => (string) __('mybonus.text_success_invites'),
            'tmp_invite' => (string) __('mybonus.text_success_tmp_invites'),
            'vip' => (string) __('mybonus.text_success_vip'),
            'vipfalse' => (string) __('mybonus.text_error_bang'),
            'title' => (string) __('mybonus.text_success_custom_title'),
            'transfer' => 'You have spread the',
            'charity' => (string) __('mybonus.text_success_charity'),
            'cancel_hr' => (string) __('mybonus.text_success_cancel_hr'),
            'attendance_card' => (string) __('mybonus.text_success_buy_attendance_card'),
            'rainbow_id' => (string) __('mybonus.text_success_buy_rainbow_id'),
            'change_username_card' => (string) __('mybonus.text_success_buy_change_username_card'),
            'duplicated' => $lockText,
        ];

        foreach ($expect as $do => $needle) {
            $result = $this->service->build(
                $this->requestWithQuery(['action' => 'exchange', 'do' => $do]),
            )->toArray();
            $this->assertStringContainsString(
                $needle,
                (string) $result['msg'],
                "do={$do} must render its message arm",
            );
        }

        $titleMsg = (string) $this->service->build(
            $this->requestWithQuery(['action' => 'exchange', 'do' => 'title']),
        )->toArray()['msg'];
        $this->assertStringContainsString('My Title', $titleMsg);
    }
}
