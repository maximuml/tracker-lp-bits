<?php

namespace Tests\Unit\Support;

use App\Support\Menu;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
class MenuTest extends TestCase
{
    public function test_custom_menu_short_circuits_database_lookup(): void
    {
        $result = (new Menu)->render(
            scriptName: 'index.php',
            enableOffer: 'no',
            customMenu: '<b>Custom</b>',
        );

        $this->assertSame('home', $result['selected']);
        $this->assertStringContainsString('<b>Custom</b>', $result['html']);
    }

    public function test_selected_item_matches_script_name(): void
    {
        $result = (new Menu)->render(
            scriptName: 'forums.php',
            enableOffer: 'no',
            customMenu: '<b>Custom</b>',
        );

        $this->assertSame('forums', $result['selected']);
    }
}
