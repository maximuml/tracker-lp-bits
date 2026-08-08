<?php

namespace Tests\Unit\Support;

use App\Support\Logger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class LoggerTest extends TestCase
{
    private string|false|null $originalLogDir = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLogDir = getenv('NEXUS_LOG_DIR');
        $this->resetLoggerStaticState();
    }

    protected function tearDown(): void
    {
        if ($this->originalLogDir === false) {
            putenv('NEXUS_LOG_DIR');
        } else {
            putenv('NEXUS_LOG_DIR=' . $this->originalLogDir);
        }
        $this->resetLoggerStaticState();
        parent::tearDown();
    }

    private function resetLoggerStaticState(): void
    {
        $reflection = new ReflectionClass(Logger::class);

        $logLevel = $reflection->getProperty('logLevel');
        $logLevel->setValue(null, null);

        $appEnv = $reflection->getProperty('appEnv');
        $appEnv->setValue(null, null);

        $filePaths = $reflection->getProperty('filePaths');
        $filePaths->setValue(null, []);
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

    public function test_write_uses_passed_user_and_passkey(): void
    {
        $dir = sys_get_temp_dir() . '/nexus-logger-test-' . uniqid();
        mkdir($dir, 0777, true);

        putenv('NEXUS_LOG_DIR=' . $dir);
        putenv('LOG_LEVEL=debug');
        putenv('APP_ENV=testing');

        $_REQUEST['passkey'] = 'request-passkey';

        try {
            Logger::write('unit message', 'info', false, ['id' => 42, 'passkey' => 'user-passkey'], 'explicit-passkey');

            $path = Logger::filePath();
            $this->assertFileExists($path);
            $content = file_get_contents($path);
            $this->assertStringContainsString('[42]', $content);
            $this->assertStringContainsString('[explicit-passkey]', $content);
            $this->assertStringContainsString('unit message', $content);
            $this->assertStringNotContainsString('request-passkey', $content);
        } finally {
            @unlink(Logger::filePath());
            @rmdir($dir);
            unset($_REQUEST['passkey']);
        }
    }

    public function test_write_defaults_to_zero_and_empty_passkey(): void
    {
        $dir = sys_get_temp_dir() . '/nexus-logger-test-' . uniqid();
        mkdir($dir, 0777, true);

        putenv('NEXUS_LOG_DIR=' . $dir);
        putenv('LOG_LEVEL=debug');
        putenv('APP_ENV=testing');

        try {
            Logger::write('default message');

            $path = Logger::filePath();
            $this->assertFileExists($path);
            $content = file_get_contents($path);
            $this->assertStringContainsString('[0]', $content);
            $this->assertStringContainsString(' default message', $content);
        } finally {
            @unlink(Logger::filePath());
            @rmdir($dir);
        }
    }
}
