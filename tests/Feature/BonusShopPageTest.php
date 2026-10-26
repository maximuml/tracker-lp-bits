<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
final class BonusShopPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'bonus.bonusgift' => 'yes',
            'bonus.onegbupload' => '300',
            'bonus.fivegbupload' => '600',
            'bonus.tengbupload' => '1200',
            'bonus.oneinvite' => '500',
            'bonus.one_tmp_invite' => '500',
            'bonus.customtitle' => '5000',
            'hr.mode' => 'disabled',
        ] as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'autoload' => 1],
            );
        }
        Settings::resetCache();
    }

    protected function tearDown(): void
    {
        Settings::resetCache();
        parent::tearDown();
    }

    public function test_shop_groups_and_chips_use_canonical_ordered_anchors(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1, 'seedbonus' => 150]);

        $response = $this->withNexusCookie($user)->get('/web/mybonus');

        $response->assertOk()
            ->assertSeeInOrder([
                'id="bonus-upload"',
                'id="bonus-download"',
                'id="bonus-invites"',
                'id="bonus-profile"',
                'id="bonus-gifts"',
            ])
            ->assertSee('href="/web/mybonus#bonus-upload"', false)
            ->assertSee('href="/web/mybonus#bonus-download"', false)
            ->assertSee('href="/web/mybonus#bonus-invites"', false)
            ->assertSee('href="/web/mybonus#bonus-profile"', false)
            ->assertSee('href="/web/mybonus#bonus-gifts"', false);
    }

    public function test_each_shop_item_has_the_exchange_form_contract(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1, 'seedbonus' => 150]);
        $response = $this->withNexusCookie($user)->get('/web/mybonus');
        $html = (string) $response->getContent();

        preg_match_all('/<form id="bx\d+" action="\/web\/mybonus\/exchange" method="post">.*?<\/form>/s', $html, $forms);
        $this->assertNotEmpty($forms[0]);
        $this->assertSame(substr_count($html, '<article class="nx-ucard nx-bshop__item'), count($forms[0]));
        foreach ($forms[0] as $form) {
            $this->assertStringContainsString('<input type="hidden" name="option" value="', $form);
            $this->assertStringContainsString('name="submit"', $form);
        }

        $names = [];
        foreach ($forms[0] as $form) {
            preg_match_all('/\bname="([^"]+)"/', $form, $formNames);
            $names = array_merge($names, $formNames[1]);
        }
        $names = array_values(array_unique(array_filter($names)));
        sort($names);

        $expected = ['_token', 'bonuscharity', 'bonusgift', 'message', 'option', 'ratiocharity', 'submit', 'title', 'username'];
        sort($expected);
        $this->assertSame($expected, $names);
        $response->assertDontSee('name="hr_id"', false);
    }

    public function test_unaffordable_upload_shows_progress_and_points_needed(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1, 'seedbonus' => 150]);

        $this->withNexusCookie($user)
            ->get('/web/mybonus')
            ->assertOk()
            ->assertSee('max="300"', false)
            ->assertSee('150 more points needed')
            ->assertSee('disabled', false);
    }

    public function test_affordable_item_has_primary_enabled_button(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1, 'seedbonus' => 10000]);

        $response = $this->withNexusCookie($user)->get('/web/mybonus');

        $response->assertOk();
        $this->assertMatchesRegularExpression('/class="nx-btn nx-btn--primary"[^>]*value="Exchange!"/', (string) $response->getContent());
    }

    public function test_gift_labels_and_info_disclosure_are_accessible(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1, 'seedbonus' => 150]);

        $this->withNexusCookie($user)
            ->get('/web/mybonus')
            ->assertOk()
            ->assertSee('<label for="giftusername">', false)
            ->assertSee('<label for="giftcustom">', false)
            ->assertSee('<label for="giftmessage">', false)
            ->assertSee('<label for="ratiocharity">', false)
            ->assertSee('<label for="charityselect">', false)
            ->assertSee('<details class="nx-bshop__info">', false)
            ->assertDontSee('<details class="nx-bshop__info" open', false);
    }

    public function test_do_upload_renders_success_notice(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1, 'seedbonus' => 150]);

        $this->withNexusCookie($user)
            ->get('/web/mybonus?do=upload')
            ->assertOk()
            ->assertSee('role="status"', false)
            ->assertSee(__('mybonus.text_success_upload'));
    }
}
