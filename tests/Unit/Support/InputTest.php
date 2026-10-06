<?php

namespace Tests\Unit\Support;

use App\Support\Input;
use App\Support\NexusContext;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
final class InputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        NexusContext::reset();
    }

    public function test_unescape_returns_value_unchanged(): void
    {
        $this->assertSame('hello', Input::unescape('hello'));
        $this->assertSame(123, Input::unescape(123));
        $this->assertNull(Input::unescape(null));
    }
}
