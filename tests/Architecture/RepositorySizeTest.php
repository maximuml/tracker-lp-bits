<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * W0-04 / W4-05 / step 1.3: Ratchet on class cohesion in app/Repositories
 * and app/Services.
 *
 * God-object repositories are a recognised tech-debt hotspot. The original
 * ratchet capped file length at MAX_LINES_NEW_FILE — a Goodhart metric:
 * classes were split mechanically to stay under the line limit regardless
 * of cohesion (9 of the 10 largest services sat in the 350-400 corridor).
 * Step 1.3 replaced it with cohesion metrics and merged those
 * counter-splits back:
 *   - MAX_PUBLIC_METHODS per class (public API surface);
 *   - MAX_CONSTRUCTOR_DEPS per class (dependency fan-out);
 *   - a ban on generic Manager/Helper/Utils class names.
 *
 * Raw file length is no longer gated — a cohesive class may be long.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class RepositorySizeTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /** Maximum public methods for any new file in Repositories or Services. */
    private const MAX_PUBLIC_METHODS_NEW_FILE = 20;

    /** Maximum constructor dependencies for any new file. */
    private const MAX_CONSTRUCTOR_DEPS = 8;

    /**
     * Baseline: files that exceed MAX_CONSTRUCTOR_DEPS at capture time.
     * Map of relative path => constructor dependency count.
     *
     * @var array<string, int>
     */
    private const BASELINE_CONSTRUCTOR_DEPS = [
        'app/Repositories/TorrentSearchRepository.php' => 12,
        'app/Services/AjaxService.php' => 13,
        'app/Services/AnnounceService.php' => 13, // ADR 0004: pipeline deps, do not merge/split
        'app/Services/Cleanup/Tasks.php' => 11,
        'app/Services/ForumIndexService.php' => 9,
        'app/Services/ForumModerationService.php' => 10,
        'app/Services/ForumService.php' => 11,
    ];

    /**
     * Baseline: files that exceed MAX_PUBLIC_METHODS_NEW_FILE at capture time.
     * Map of relative path => public method count.
     *
     * @var array<string, int>
     */
    private const BASELINE_PUBLIC_METHODS = [];

    /**
     * Baseline: files with generic class names that predate the W4-05 ban.
     *
     * @var array<string, string>
     */
    private const BASELINE_GENERIC_NAMES = [
        'app/Services/Captcha/CaptchaManager.php' => 'Manager',
    ];

    public function test_no_new_files_with_too_many_public_methods(): void
    {
        $dirs = ['app/Repositories', 'app/Services'];
        $violations = [];

        foreach ($dirs as $dir) {
            $absDir = self::APP_DIR.'/'.basename($dir);
            if (! is_dir($absDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = $dir.'/'.ltrim(str_replace($absDir, '', $file->getPathname()), '/');
                $methodCount = $this->countPublicMethods($file->getPathname());

                if ($methodCount <= self::MAX_PUBLIC_METHODS_NEW_FILE) {
                    continue;
                }

                if (array_key_exists($relativePath, self::BASELINE_PUBLIC_METHODS)) {
                    if ($methodCount > self::BASELINE_PUBLIC_METHODS[$relativePath]) {
                        $violations[] = sprintf(
                            '%s grew from baseline %d to %d public methods. Extract methods to a collaborator or update the baseline after reducing another file.',
                            $relativePath,
                            self::BASELINE_PUBLIC_METHODS[$relativePath],
                            $methodCount,
                        );
                    }
                } else {
                    $violations[] = sprintf(
                        '%s has %d public methods (max %d for new files). Extract methods to a collaborator.',
                        $relativePath,
                        $methodCount,
                        self::MAX_PUBLIC_METHODS_NEW_FILE,
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Found files with too many public methods:\n".
            implode("\n", $violations),
        );
    }

    public function test_no_new_files_with_too_many_constructor_deps(): void
    {
        $dirs = ['app/Repositories', 'app/Services'];
        $violations = [];

        foreach ($dirs as $dir) {
            $absDir = self::APP_DIR.'/'.basename($dir);
            if (! is_dir($absDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = $dir.'/'.ltrim(str_replace($absDir, '', $file->getPathname()), '/');
                $depCount = $this->countConstructorDeps($file->getPathname());

                if ($depCount <= self::MAX_CONSTRUCTOR_DEPS) {
                    continue;
                }

                if (array_key_exists($relativePath, self::BASELINE_CONSTRUCTOR_DEPS)) {
                    if ($depCount > self::BASELINE_CONSTRUCTOR_DEPS[$relativePath]) {
                        $violations[] = sprintf(
                            '%s grew from baseline %d to %d constructor dependencies. Inject a narrower collaborator or update the baseline after reducing another file.',
                            $relativePath,
                            self::BASELINE_CONSTRUCTOR_DEPS[$relativePath],
                            $depCount,
                        );
                    }
                } else {
                    $violations[] = sprintf(
                        '%s has %d constructor dependencies (max %d for new files). Inject a narrower collaborator or a facade for the extra concerns.',
                        $relativePath,
                        $depCount,
                        self::MAX_CONSTRUCTOR_DEPS,
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Found files with too many constructor dependencies:\n".
            implode("\n", $violations),
        );
    }

    public function test_no_generic_manager_helper_utils_class_names(): void
    {
        $dirs = ['app/Repositories', 'app/Services'];
        $violations = [];
        $bannedSuffixes = ['Manager', 'Helper', 'Utils'];

        foreach ($dirs as $dir) {
            $absDir = self::APP_DIR.'/'.basename($dir);
            if (! is_dir($absDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $className = $file->getBasename('.php');

                foreach ($bannedSuffixes as $suffix) {
                    if (str_ends_with($className, $suffix)) {
                        $relativePath = $dir.'/'.ltrim(str_replace($absDir, '', $file->getPathname()), '/');
                        if (array_key_exists($relativePath, self::BASELINE_GENERIC_NAMES)) {
                            continue;
                        }
                        $violations[] = sprintf(
                            '%s: class name ends with "%s" — use a domain-specific name instead.',
                            $relativePath,
                            $suffix,
                        );
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Found generic class names that should be replaced with domain-specific names:\n".
            implode("\n", $violations),
        );
    }

    private function countPublicMethods(string $path): int
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return 0;
        }

        // __construct is DI wiring, not public API surface — exclude it.
        return preg_match_all('/^\s*public\s+(?:static\s+)?function\s+(?!__construct\b)/m', $content) ?: 0;
    }

    private function countConstructorDeps(string $path): int
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return 0;
        }

        if (! preg_match('/function __construct\s*\((.*?)\)\s*[:{]/s', $content, $m)) {
            return 0;
        }

        // Count promoted or assigned parameters — each comma-separated
        // parameter is one injected dependency.
        $params = trim($m[1]);
        if ($params === '') {
            return 0;
        }

        return substr_count($params, ',') + 1;
    }
}
