<?php

namespace Tests\Integration\Support;

use App\Support\Time;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
class TimeRenderTest extends TestCase
{
    public function test_format_absolute_time_replaces_spaces_with_br_in_twoline(): void
    {
        // Legacy `str_replace(" ", "<br />", ...)` is global — every
        // space is replaced, not just the date/time separator.
        $this->assertSame('2026-05-18<br /> 04:00:00', Time::formatAbsoluteTime('2026-05-18 04:00:00', true));
        $this->assertSame('a<br /> b<br /> c', Time::formatAbsoluteTime('a b c', true));
    }

    public function test_format_absolute_time_empty_string_round_trips(): void
    {
        $this->assertSame('', Time::formatAbsoluteTime('', false));
        $this->assertSame('', Time::formatAbsoluteTime('', true));
    }
}
