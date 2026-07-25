<?php

namespace Tests\Unit\Support;

use App\Support\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private ?string $originalLogDir = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLogDir = getenv('NEXUS_LOG_DIR');
    }

    protected function tearDown(): void
    {
        if ($this->originalLogDir === false) {
            putenv('NEXUS_LOG_DIR');
        } else {
            putenv('NEXUS_LOG_DIR=' . $this->originalLogDir);
        }
        parent::tearDown();
    }

    public function test_file_path_uses_nexus_log_dir_env(): void
    {
        $dir = sys_get_temp_dir();
        putenv('NEXUS_LOG_DIR=' . $dir);

        $path = Logger::filePath('unit');

        $this->assertStringStartsWith($dir . '/nexus', $path);
        $this->assertStringContainsString('-unit-', $path);
        $this->assertStringEndsWith('.log', $path);
    }
}
