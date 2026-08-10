<?php

namespace Tests\Unit\Support;

use App\Support\Menu;
use PHPUnit\Framework\TestCase;

class MenuTest extends TestCase
{
    public function test_custom_menu_short_circuits_database_lookup(): void
    {
        $result = Menu::render(
            scriptName: 'index.php',
            langFunctions: ['text_home' => 'Home'],
            enableOffer: 'no',
            customMenu: '<b>Custom</b>',
        );

        $this->assertSame('home', $result['selected']);
        $this->assertStringContainsString('<b>Custom</b>', $result['html']);
    }

    public function test_selected_item_matches_script_name(): void
    {
        $result = Menu::render(
            scriptName: 'forums.php',
            langFunctions: ['text_home' => 'Home', 'text_forums' => 'Forums'],
            enableOffer: 'no',
            customMenu: '<b>Custom</b>',
        );

        $this->assertSame('forums', $result['selected']);
    }
}
