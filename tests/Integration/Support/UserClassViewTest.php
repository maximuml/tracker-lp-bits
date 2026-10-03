<?php

namespace Tests\Integration\Support;

use App\Support\UserClass;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * View-dependent assertions extracted from `tests/Unit/Support/UserClassTest.php`
 * — `name()` wraps the colored class name through a Blade partial.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UserClassViewTest extends TestCase
{
    public function test_name_colored_wraps_in_bold_tag(): void
    {
        $this->assertSame("<b class='User_Name'>User</b>", (string) UserClass::name(1, false, true, false));
    }
}
