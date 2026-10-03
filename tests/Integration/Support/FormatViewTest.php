<?php

namespace Tests\Integration\Support;

use App\Support\Format;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * View-dependent assertions extracted from `tests/Unit/Support/FormatTest.php`
 * — `sizeCompact()` renders its `<br />` separator through a Blade partial.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class FormatViewTest extends TestCase
{
    public function test_size_compact_uses_br_separator(): void
    {
        $this->assertSame('1.00<br />KB', Format::sizeCompact(1024));
        $this->assertSame('1.00<br />MB', Format::sizeCompact(1048576));
    }
}
