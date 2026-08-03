<?php

namespace Tests\Unit\Support;

use App\Support\Debug;
use PHPUnit\Framework\TestCase;

final class DebugTest extends TestCase
{
    public function test_print_line_outputs_timestamp_and_line(): void
    {
        ob_start();
        Debug::printLine('test message');
        $output = ob_get_clean();

        $this->assertStringStartsWith('[', $output);
        $this->assertStringEndsWith('test message<br />', $output);
        $this->assertMatchesRegularExpression('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] test message<br \/>$/', $output);
    }
}
