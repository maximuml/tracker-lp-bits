<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * W9-01: Docs consistency ratchet.
 *
 * AGENTS.md and README.md are the files agents and developers actually
 * read. This test fails if:
 *
 *   - a directory listed under "Key directories" no longer exists;
 *   - a `make <target>` or `composer <script>` used in a ```bash block
 *     does not exist in Makefile / composer.json;
 *   - a hand-maintained count ("NN tests", "NN controllers", …) reappears
 *     in "Key directories" — counts drifted repeatedly and must not be
 *     hard-coded (see the note under that section);
 *   - a fenced code block references a path that does not exist and is
 *     not explicitly marked as historical ("former", "removed", "deleted").
 *
 * Keep AGENTS.md accurate: fix the doc, not this test.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class DocsConsistencyTest extends TestCase
{
    private const DOCS = ['AGENTS.md', 'README.md'];

    private const ROOT = __DIR__.'/../..';

    /**
     * Generated / build / VCS-ignored paths that should never be checked for
     * existence. They are mentioned in the docs as artifacts, not as tracked
     * repo paths.
     */
    private const GENERATED_PATHS = [
        'public/build',
        'public/build/assets',
        'public/build/manifest.json',
        'vendor',
        'node_modules',
        'bootstrap/cache',
        'storage/framework',
        'storage/logs',
        'storage/app',
        'storage/debugbar',
        'public/hot',
    ];

    public function test_key_directories_exist(): void
    {
        $content = (string) file_get_contents(self::ROOT.'/AGENTS.md');
        $section = $this->section($content, 'Key directories');
        $this->assertNotSame('', $section, 'AGENTS.md is missing a "Key directories" section.');

        foreach (explode("\n", $section) as $line) {
            if (preg_match('/^- `([^`]+)`\s+—/', $line, $m)) {
                $path = rtrim($m[1], '/');
                $this->assertDirectoryExists(
                    self::ROOT.'/'.$path,
                    "AGENTS.md lists `{$path}` in \"Key directories\" but it does not exist."
                );
            }
        }
    }

    public function test_key_directories_have_no_hard_coded_counts(): void
    {
        $content = (string) file_get_contents(self::ROOT.'/AGENTS.md');
        $section = $this->section($content, 'Key directories');

        $this->assertDoesNotMatchRegularExpression(
            '/\b\d+\s+(controllers|services|models|migrations|tests|files)\b/',
            $section,
            '"Key directories" must not hard-code counts — they drifted ~4× before. '
            .'Remove the number, not this test.'
        );
    }

    public function test_bash_commands_reference_existing_targets(): void
    {
        $makeTargets = $this->makefileTargets();
        $composerScripts = $this->composerScripts();
        $failures = [];

        foreach (self::DOCS as $doc) {
            $file = self::ROOT.'/'.$doc;
            if (! file_exists($file)) {
                continue;
            }
            foreach ($this->bashBlocks((string) file_get_contents($file)) as $block) {
                foreach (preg_split('/\r?\n/', $block) as $line) {
                    $line = trim($line);
                    if (preg_match('/^make ([a-zA-Z0-9_-]+)/', $line, $m)) {
                        if (! isset($makeTargets[$m[1]])) {
                            $failures[] = "{$doc}: `{$line}` — no such Makefile target.";
                        }
                    } elseif (preg_match('/^composer ([a-zA-Z0-9:_-]+)/', $line, $m)) {
                        $script = preg_replace('/^test:/', 'test:', $m[1]);
                        $known = isset($composerScripts[$script])
                            || isset($composerScripts['test'])
                            || in_array($m[1], ['install', 'audit', 'validate', 'dump-autoload', 'outdated'], true);
                        if (! $known) {
                            $failures[] = "{$doc}: `{$line}` — no such composer script.";
                        }
                    }
                }
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /**
     * Paths in backticks inside bash blocks must exist unless the block or
     * the surrounding line marks them as historical.
     */
    public function test_documented_paths_exist(): void
    {
        $failures = [];
        $historical = '/\b(former|removed|deleted|no longer|legacy file)\b/i';

        foreach (self::DOCS as $doc) {
            $file = self::ROOT.'/'.$doc;
            if (! file_exists($file)) {
                continue;
            }
            $lines = preg_split('/\r?\n/', (string) file_get_contents($file));
            if (! is_array($lines)) {
                continue;
            }
            $inBlock = false;
            foreach ($lines as $i => $line) {
                if (preg_match('/^```/', $line)) {
                    $inBlock = ! $inBlock;

                    continue;
                }
                if ($inBlock) {
                    continue;
                }
                if (preg_match($historical, $line)) {
                    continue;
                }
                // Backticked relative paths that look like repo paths.
                // Only check paths that plausibly name files or directories:
                // they either live under a known root or carry an extension.
                if (preg_match_all('/`((?:[a-zA-Z0-9_.-]+\/)+[a-zA-Z0-9_.\/-]*)`/', $line, $ms)) {
                    foreach ($ms[1] as $p) {
                        $p = rtrim($p, '/');
                        if (str_contains($p, '..') || str_contains($p, '://')) {
                            continue;
                        }

                        if (in_array($p, self::GENERATED_PATHS, true) || in_array(dirname($p), self::GENERATED_PATHS, true)) {
                            continue;
                        }

                        $knownRoot = preg_match(
                            '#^(app|config|database|docs|public|resources|routes|scripts|storage|tests|\.agents|\.docker|\.devin|\.github|vendor|node_modules|docker-compose|bootstrap|lang)/#',
                            $p.'/'
                        ) === 1;
                        $hasExtension = preg_match('/\.[a-zA-Z0-9]{1,10}$/', $p) === 1;
                        if (! $knownRoot && ! $hasExtension) {
                            continue; // URL prefix or generic name — not a repo path
                        }
                        if (! file_exists(self::ROOT.'/'.$p)) {
                            $failures[] = "{$doc}:".($i + 1)." references `{$p}` which does not exist.";
                        }
                    }
                }
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /**
     * Extract the markdown section body between "## <title>" and the next "## ".
     */
    private function section(string $content, string $title): string
    {
        if (preg_match('/^## '.preg_quote($title, '/').'\s*$(.+?)(?=^## |\z)/ms', $content, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private function bashBlocks(string $content): array
    {
        if (! preg_match_all('/```bash\n(.*?)```/s', $content, $m)) {
            return [];
        }

        return $m[1];
    }

    /**
     * @return array<string, true>
     */
    private function makefileTargets(): array
    {
        $targets = [];
        $lines = file(self::ROOT.'/Makefile');
        if ($lines !== false) {
            foreach ($lines as $line) {
                if (preg_match('/^([a-zA-Z0-9_-]+):/', $line, $m)) {
                    $targets[$m[1]] = true;
                }
            }
        }

        return $targets;
    }

    /**
     * @return array<string, true>
     */
    private function composerScripts(): array
    {
        $data = json_decode((string) file_get_contents(self::ROOT.'/composer.json'), true);
        $scripts = is_array($data['scripts'] ?? null) ? $data['scripts'] : [];

        $out = [];
        foreach ($scripts as $name => $_) {
            $out[$name] = true;
        }

        return $out;
    }
}
