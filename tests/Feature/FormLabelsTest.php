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
final class FormLabelsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::resetCache();
    }

    protected function tearDown(): void
    {
        Settings::resetCache();
        parent::tearDown();
    }

    public function test_tracker_uses_catalog_defaults_card_heading(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1]);

        $response = $this->withNexusCookie($user)->get('/usercp?action=tracker');

        $response->assertOk()
            ->assertSee('Catalog defaults')
            ->assertSee('id="ucp-sec-catalog"', false);
    }

    public function test_tracker_selects_have_accessible_names(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => 1]);

        $response = $this->withNexusCookie($user)->get('/usercp?action=tracker');

        $response->assertOk()
            ->assertSee('<label class="nx-setrow__label" for="usercp-stylesheet">'.e(__('usercp.row_stylesheet')).'</label>', false)
            ->assertSee('<label for="usercp-incldead">'.e(__('usercp.text_show_dead_active')).'</label>', false)
            ->assertSee('id="usercp-incldead"', false)
            ->assertSee('id="usercp-stylesheet"', false);
    }

    public function test_bonus_shop_controls_have_accessible_labels(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'bonus.bonusgift'],
            ['value' => 'yes', 'autoload' => 1]
        );
        DB::table('settings')->updateOrInsert(
            ['name' => 'bonus.customtitle'],
            ['value' => '1', 'autoload' => 1]
        );
        Settings::resetCache();

        /** @var User $user */
        $user = User::factory()->create(['class' => 1]);
        $response = $this->withNexusCookie($user)->get('/web/mybonus');

        $response->assertOk()
            ->assertSee('<label for="bonustitle">'.e(__('mybonus.text_special_title')).'</label>', false)
            ->assertSee('<label for="giftusername">'.e(__('mybonus.text_username')).'</label>', false)
            ->assertSee('id="giftusername"', false)
            ->assertSee('<label for="giftcustom">'.e(__('mybonus.text_to_be_given')).'</label>', false)
            ->assertSee('id="giftcustom"', false)
            ->assertSee('<label for="giftmessage">'.e(__('mybonus.text_message')).'</label>', false)
            ->assertSee('id="giftmessage"', false)
            ->assertSee('<label for="ratiocharity">'.e(__('mybonus.text_ratio_below')).'</label>', false)
            ->assertSee('id="ratiocharity"', false)
            ->assertSee('<label for="charityselect">'.e(__('mybonus.text_to_be_given')).'</label>', false)
            ->assertSee('id="charityselect"', false);
    }
}
