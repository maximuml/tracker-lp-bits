<?php

namespace Tests\Unit\Support;

use App\Support\Path;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandbox = sys_get_temp_dir().'/nexus-path-test-'.bin2hex(random_bytes(6));
        mkdir($this->sandbox, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->sandbox);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $dir.DIRECTORY_SEPARATOR.$entry;
            if (is_dir($full)) {
                $this->rrmdir($full);
            } else {
                unlink($full);
            }
        }
        rmdir($dir);
    }

    public function test_resolve_returns_existing_absolute_file_verbatim(): void
    {
        $file = $this->sandbox.'/foo.txt';
        file_put_contents($file, 'hello');

        $this->assertSame($file, Path::resolve($file, '/unused-root/'));
    }

    public function test_resolve_returns_realpath_when_absolute_dir(): void
    {
        $sub = $this->sandbox.'/dir1';
        mkdir($sub);

        $this->assertSame(realpath($sub), Path::resolve($sub, '/unused-root/'));
    }

    public function test_resolve_prepends_root_for_relative_dir(): void
    {
        $rootPath = $this->sandbox.'/';
        $bucket = 'nexus-bucket-'.bin2hex(random_bytes(4));
        mkdir($this->sandbox.'/'.$bucket);

        $this->assertSame(
            realpath($this->sandbox.'/'.$bucket),
            Path::resolve($bucket, $rootPath),
        );
    }

    public function test_resolve_returns_input_when_neither_file_nor_dir(): void
    {
        $rootPath = $this->sandbox.'/';
        $missing = 'nexus-missing-'.bin2hex(random_bytes(4));

        $this->assertSame(
            $rootPath.$missing,
            Path::resolve($missing, $rootPath),
        );
    }

    public function test_resolve_does_not_prepend_root_to_existing_file(): void
    {
        $file = $this->sandbox.'/keep.txt';
        file_put_contents($file, '');

        $this->assertSame($file, Path::resolve($file, '/wrong-root/'));
    }

    public function test_resolve_empty_string_falls_back_to_root_prepended_when_root_is_dir(): void
    {
        $rootPath = $this->sandbox.'/';

        $this->assertSame(realpath($rootPath), Path::resolve('', $rootPath));
    }

    public function test_make_folder_creates_new_directory_recursively(): void
    {
        $rootPath = $this->sandbox.'/';
        $path = Path::makeFolder('cache/', 'sub/deeper', $rootPath);

        $this->assertSame($rootPath.'cache/sub/deeper', $path);
        $this->assertDirectoryExists($path);
    }

    public function test_make_folder_strips_leading_dot_and_slash_chars(): void
    {
        $rootPath = $this->sandbox.'/';

        $this->assertSame(
            $rootPath.'cache/foo',
            Path::makeFolder('./cache/', 'foo', $rootPath),
        );
        $this->assertSame(
            $rootPath.'cache/bar',
            Path::makeFolder('/cache/', 'bar', $rootPath),
        );
        $this->assertSame(
            $rootPath.'cache/baz',
            Path::makeFolder('.../cache/', 'baz', $rootPath),
        );
    }

    public function test_make_folder_returns_path_even_if_already_exists(): void
    {
        $rootPath = $this->sandbox.'/';
        mkdir($this->sandbox.'/existing', 0777, true);

        $this->assertSame($rootPath.'existing', Path::makeFolder('existing', '', $rootPath));
        $this->assertSame($rootPath.'existing', Path::makeFolder('', 'existing', $rootPath));
    }

    public function test_make_folder_concatenates_prefix_and_name_in_order(): void
    {
        $rootPath = $this->sandbox.'/';
        $path = Path::makeFolder('a/', 'b/c', $rootPath);

        $this->assertSame($rootPath.'a/b/c', $path);
        $this->assertDirectoryExists($path);
    }

    public function test_make_folder_uses_zero_seven_seven_seven_mode(): void
    {
        $rootPath = $this->sandbox.'/';
        $path = Path::makeFolder('perm-test', '', $rootPath);

        $this->assertDirectoryExists($path);
        $mode = fileperms($path) & 0777;
        $this->assertNotSame(0, $mode);
    }

    // ---------- categoryFolder() ----------

    public function test_category_folder_assembles_mode_and_icon_without_language(): void
    {
        $this->assertSame(
            'category/movie/default',
            Path::categoryFolder('movie', 'default', false, 'en'),
        );
    }

    public function test_category_folder_appends_language_when_multilang(): void
    {
        $this->assertSame(
            'category/movie/default/chs',
            Path::categoryFolder('movie', 'default', true, 'chs'),
        );
    }

    public function test_category_folder_trims_slashes_from_each_segment(): void
    {
        // Leading/trailing slashes on any input must collapse so the
        // result never contains an empty `//` segment.
        $this->assertSame(
            'category/movie/default/en',
            Path::categoryFolder('/movie/', '/default/', true, '/en/'),
        );
    }

    public function test_category_folder_ignores_language_dir_when_not_multilang(): void
    {
        // Even with a non-empty langDir, the segment is omitted unless
        // the icon set is flagged multilingual.
        $this->assertSame(
            'category/music/blue',
            Path::categoryFolder('music', 'blue', false, 'en'),
        );
    }

    public function test_category_folder_handles_empty_segments(): void
    {
        $this->assertSame('category//', Path::categoryFolder('', '', false, 'en'));
        // multilang appends a `/` plus the (empty) trimmed langDir.
        $this->assertSame('category///', Path::categoryFolder('', '', true, ''));
    }
}
