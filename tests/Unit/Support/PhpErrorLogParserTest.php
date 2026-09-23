<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PhpErrorLogParser;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
final class PhpErrorLogParserTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = tempnam(sys_get_temp_dir(), 'php_err_');
        self::assertNotFalse($this->logFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->logFile);
        parent::tearDown();
    }

    public function test_parses_entries_newest_first(): void
    {
        file_put_contents($this->logFile, implode("\n", [
            '[19-Sep-2026 10:00:00 UTC] PHP Warning:  Undefined variable $x in /var/www/html/a.php on line 5',
            '[19-Sep-2026 11:00:00 UTC] PHP Fatal error:  Uncaught TypeError: foo() in /var/www/html/b.php:10',
        ]));

        $entries = PhpErrorLogParser::entries($this->logFile);

        $this->assertCount(2, $entries);
        $this->assertSame('fatal', $entries[0]['level_key']);
        $this->assertSame('warning', $entries[1]['level_key']);
        $this->assertSame('2026-09-19 11:00:00', $entries[0]['time']);
        $this->assertSame('2026-09-19 10:00:00', $entries[1]['time']);
        $this->assertStringContainsString('Undefined variable', $entries[1]['message']);
    }

    public function test_attaches_stack_trace_continuation_lines(): void
    {
        file_put_contents($this->logFile, implode("\n", [
            '[19-Sep-2026 11:00:00 UTC] PHP Fatal error:  Uncaught Exception: boom in /var/www/html/a.php:5',
            'Stack trace:',
            '#0 /var/www/html/b.php(10): foo()',
            '#1 {main}',
            '  thrown in /var/www/html/a.php on line 5',
            '[19-Sep-2026 11:05:00 UTC] PHP Notice:  hi in /var/www/html/c.php on line 1',
        ]));

        $entries = PhpErrorLogParser::entries($this->logFile);

        $this->assertCount(2, $entries);
        $this->assertStringContainsString('#0 /var/www/html/b.php', $entries[1]['context']);
        $this->assertStringContainsString('thrown in', $entries[1]['full']);
        $this->assertSame('', $entries[0]['context']);
    }

    public function test_missing_file_returns_empty(): void
    {
        $this->assertSame([], PhpErrorLogParser::entries('/nonexistent/php_errors.log'));
    }

    public function test_limit_caps_entries(): void
    {
        $lines = [];
        for ($i = 0; $i < 20; $i++) {
            $lines[] = "[19-Sep-2026 10:$i:00 UTC] PHP Notice:  n$i in /a.php on line 1";
        }
        file_put_contents($this->logFile, implode("\n", $lines));

        $this->assertCount(5, PhpErrorLogParser::entries($this->logFile, limit: 5));
    }
}
