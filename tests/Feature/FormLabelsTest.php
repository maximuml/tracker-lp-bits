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

    public function test_default_categories_label_renders_its_line_break(): void
    {
        $user = User::factory()->create(['class' => 1]);

        $response = $this->withNexusCookie($user)->get('/usercp?action=tracker');

        $response->assertOk()
            ->assertSee('Default<br />categories', false)
            ->assertDontSee('Default&lt;br /&gt;categories', false);
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

        $user = User::factory()->create(['class' => 1]);
        $response = $this->withNexusCookie($user)->get('/web/mybonus');

        $response->assertOk()
            ->assertSee('aria-label="'.e(__('mybonus.text_special_title')).'"', false)
            ->assertSee('<label for="giftusername"><b>'.e(__('mybonus.text_username')).'</b></label>', false)
            ->assertSee('id="giftusername"', false)
            ->assertSee('<label for="giftcustom"><b>'.e(__('mybonus.text_to_be_given')).'</b></label>', false)
            ->assertSee('id="giftcustom"', false)
            ->assertSee('<label for="giftmessage"><b>'.e(__('mybonus.text_message')).'</b></label>', false)
            ->assertSee('id="giftmessage"', false)
            ->assertSee('<label for="ratiocharity">'.e(__('mybonus.text_ratio_below')).'</label>', false)
            ->assertSee('id="ratiocharity"', false)
            ->assertSee('<label for="charityselect"><b>'.e(__('mybonus.text_to_be_given')).'</b></label>', false)
            ->assertSee('id="charityselect"', false);
    }
}
