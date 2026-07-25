<?php

namespace Tests\Unit\Support;

use App\Support\Env;
use PHPUnit\Framework\TestCase;

class EnvTest extends TestCase
{
    public function test_load_and_normalize_env_file(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($file, "FOO='bar'\n# comment\nBAZ=\"qux\"\n\nEMPTY=\nTRUE=true\n");

        $env = Env::load($file);
        $this->assertSame('bar', $env['FOO']);
        $this->assertSame('qux', $env['BAZ']);
        $this->assertSame('', $env['EMPTY']);
        $this->assertSame('true', $env['TRUE']);

        unlink($file);
    }

    public function test_normalize_strips_quotes(): void
    {
        $this->assertSame('hello', Env::normalize("'hello'"));
        $this->assertSame('world', Env::normalize('"world"'));
        $this->assertSame('plain', Env::normalize('plain'));
    }
}
