<?php

namespace Tests\Unit\Support;

use App\Support\Env;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
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

    public function test_cast_converts_boolean_and_null_strings(): void
    {
        $this->assertTrue(Env::cast('true'));
        $this->assertTrue(Env::cast('TRUE'));
        $this->assertFalse(Env::cast('false'));
        $this->assertFalse(Env::cast('FALSE'));
        $this->assertNull(Env::cast('null'));
        $this->assertNull(Env::cast('NULL'));
        $this->assertSame('42', Env::cast('42'));
        $this->assertSame('plain', Env::cast('plain'));
    }

    /**
     * Real environment overrides the .env file — the same precedence as
     * Laravel's env(). Without this, phpunit <server> values (REDIS_DB=15,
     * APP_ENV=testing) and docker -e overrides never reach nexus.* config:
     * NexusCache kept writing test rows into the dev Redis keyspace.
     */
    public function test_server_and_env_superglobals_beat_dotenv_file(): void
    {
        $key = 'ENV_TEST_PRECEDENCE_KEY';
        $prevServer = $_SERVER[$key] ?? null;
        $prevEnv = $_ENV[$key] ?? null;
        try {
            $_SERVER[$key] = 'from_server';
            $this->assertSame('from_server', Env::get($key, 'fallback'));

            unset($_SERVER[$key]);
            $_ENV[$key] = 'from_env';
            $this->assertSame('from_env', Env::get($key, 'fallback'));
        } finally {
            unset($_SERVER[$key], $_ENV[$key]);
            if ($prevServer !== null) {
                $_SERVER[$key] = $prevServer;
            }
            if ($prevEnv !== null) {
                $_ENV[$key] = $prevEnv;
            }
        }
    }

    public function test_dotenv_still_used_when_env_absent(): void
    {
        // Pick a key present in .env but absent from $_ENV/$_SERVER/getenv —
        // the file remains the fallback for values only defined there.
        $dotenv = Env::load(dirname(__DIR__, 3).'/.env');
        $fileOnlyKey = null;
        foreach (array_keys($dotenv) as $key) {
            if (! array_key_exists($key, $_ENV) && ! array_key_exists($key, $_SERVER) && getenv($key) === false) {
                $fileOnlyKey = $key;
                break;
            }
        }
        if ($fileOnlyKey === null) {
            $this->markTestSkipped('no .env-only key found in this environment');
        }

        $this->assertSame($dotenv[$fileOnlyKey], Env::get($fileOnlyKey));
    }
}
