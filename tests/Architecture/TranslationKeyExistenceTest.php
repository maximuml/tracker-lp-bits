<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Ratchet: every `__('<group>.<key>')` literal used in Blade/PHP views must
 * resolve to a real entry in `resources/lang/en/<group>.php`. `__()` returns
 * the raw key for misses, so a typo ships to users as visible text (the
 * forum-search aria-label regression). Dot-less keys and dynamic keys are
 * out of scope.
 */
final class TranslationKeyExistenceTest extends TestCase
{
    private const BASE_DIR = __DIR__.'/../..';

    private const VIEW_DIRS = ['resources/views'];

    public function test_view_translation_keys_exist(): void
    {
        $keys = $this->collectKeys();
        self::assertNotEmpty($keys, 'Expected to find literal __() keys in views.');

        $missing = [];
        foreach ($keys as $file => $fileKeys) {
            foreach ($fileKeys as $key) {
                if (! $this->keyExists($key)) {
                    $missing[] = "{$key} ({$file})";
                }
            }
        }

        self::assertSame([], $missing, "Missing translation keys:\n".implode("\n", $missing));
    }

    /** @return array<string, list<string>> */
    private function collectKeys(): array
    {
        $pattern = "/__\\(\\s*'([a-z0-9_]+\\.[a-z0-9_.]+)'/";
        $found = [];

        foreach (self::VIEW_DIRS as $dir) {
            $base = self::BASE_DIR.'/'.$dir;
            if (! is_dir($base)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }
                if (! preg_match('/\.(blade\.php|php)$/', $file->getFilename())) {
                    continue;
                }
                $contents = (string) file_get_contents($file->getPathname());
                if (preg_match_all($pattern, $contents, $matches)) {
                    $relative = substr($file->getPathname(), strlen(self::BASE_DIR) + 1);
                    // Keys ending in '_' are concatenation prefixes (e.g. 'myhr.status_subline_'.$status).
                    $static = array_values(array_filter($matches[1], static fn (string $k): bool => ! str_ends_with($k, '_')));
                    $found[$relative] = array_values(array_unique($static));
                }
            }
        }

        return $found;
    }

    private function keyExists(string $key): bool
    {
        [$group, $name] = explode('.', $key, 2);
        $path = self::BASE_DIR."/resources/lang/en/{$group}.php";
        if (! is_file($path)) {
            return false;
        }
        /** @var array<string, mixed> $messages */
        $messages = require $path;

        // Lang files nest groups as arrays; walk the dotted remainder.
        foreach (explode('.', $name) as $segment) {
            if (! is_array($messages) || ! array_key_exists($segment, $messages)) {
                return false;
            }
            $messages = $messages[$segment];
        }

        return true;
    }
}
