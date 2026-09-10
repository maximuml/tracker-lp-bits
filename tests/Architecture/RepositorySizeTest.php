<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * W0-04 / W4-05: Ratchet on large files in app/Repositories and app/Services.
 *
 * God-object repositories are a recognised tech-debt hotspot. This test
 * establishes a baseline of files exceeding the line limit and fails if:
 *   - a new file exceeds MAX_LINES_NEW_FILE, or
 *   - an existing baseline file grows beyond its recorded size.
 *
 * W4-05 ratchets the new-file limit from 500 to 400 lines and adds:
 *   - a public-method count limit per class;
 *   - a ban on generic Manager/Helper/Utils class names.
 *
 * Baseline captured on 2026-09-09 (post-W4-04).
 *
 * To update after a legitimate split (e.g. extracting a service from a
 * repository), remove the entry from BASELINE_FILES and commit.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class RepositorySizeTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /** Maximum lines for any new file in Repositories or Services. */
    private const MAX_LINES_NEW_FILE = 400;

    /** Maximum public methods for any new file in Repositories or Services. */
    private const MAX_PUBLIC_METHODS_NEW_FILE = 20;

    /**
     * Baseline: files that already exceed MAX_LINES_NEW_FILE at capture time.
     * Map of relative path => line count.
     *
     * @var array<string, int>
     */
    private const BASELINE_FILES = [
        // Repositories > 400 lines (16 files)
        'app/Repositories/UserRepository.php' => 548,
        'app/Repositories/UserModerationRepository.php' => 595,
        'app/Repositories/UsercpRepository.php' => 655,
        'app/Repositories/SearchBoxRepository.php' => 527,
        'app/Repositories/ToptenRepository.php' => 525,
        'app/Repositories/TorrentModerationRepository.php' => 491,
        'app/Repositories/DashboardRepository.php' => 484,
        'app/Repositories/TorrentSearch/QueryBuilder.php' => 466,
        'app/Repositories/AttendanceRepository.php' => 455,
        'app/Repositories/ExamProgressRepository.php' => 452,
        'app/Repositories/BonusRepository.php' => 443,
        'app/Repositories/UserSearchRepository.php' => 438,
        'app/Repositories/TorrentSearch/FilterParser.php' => 424,
        'app/Repositories/CleanupRepository.php' => 414,
        // Services > 400 lines (10 files)
        'app/Services/RegistrationService.php' => 688,
        'app/Services/OfferPageService.php' => 617,
        'app/Services/ForumService.php' => 572,
        'app/Services/UsercpPageService.php' => 552,
        'app/Services/IndexPageService.php' => 549,
        'app/Services/MessageService.php' => 520,
        'app/Services/AnnounceService.php' => 493,
        'app/Services/BonusPageService.php' => 475,
        'app/Services/OfferService.php' => 422,
        'app/Services/Announce/PeerLifecycle.php' => 418,
        'app/Services/MessagePageService.php' => 417,
        'app/Services/ForumListingService.php' => 417,
        'app/Services/Cleanup/Tasks/UserClassManagementTask.php' => 430,
    ];

    /**
     * Baseline: files that exceed MAX_PUBLIC_METHODS_NEW_FILE at capture time.
     * Map of relative path => public method count.
     *
     * @var array<string, int>
     */
    private const BASELINE_PUBLIC_METHODS = [
        'app/Repositories/ForumRepository.php' => 30,
        'app/Services/AjaxService.php' => 27,
        'app/Repositories/PostRepository.php' => 26,
        'app/Repositories/OfferRepository.php' => 26,
        'app/Repositories/ExamRepository.php' => 26,
        'app/Repositories/TopicRepository.php' => 25,
        'app/Repositories/UsercpRepository.php' => 24,
        'app/Repositories/SearchBoxRepository.php' => 20,
    ];

    public function test_no_new_oversized_files_in_repositories_or_services(): void
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
                $lineCount = $this->countLines($file->getPathname());

                if ($lineCount <= self::MAX_LINES_NEW_FILE) {
                    continue;
                }

                // Check if this file is in the baseline
                if (array_key_exists($relativePath, self::BASELINE_FILES)) {
                    // Baseline file — must not grow beyond its recorded size
                    if ($lineCount > self::BASELINE_FILES[$relativePath]) {
                        $violations[] = sprintf(
                            '%s grew from baseline %d to %d lines. Split the class or update the baseline after reducing another file.',
                            $relativePath,
                            self::BASELINE_FILES[$relativePath],
                            $lineCount,
                        );
                    }
                } else {
                    // New oversized file — not in baseline
                    $violations[] = sprintf(
                        '%s is %d lines (max %d for new files). Split into smaller classes.',
                        $relativePath,
                        $lineCount,
                        self::MAX_LINES_NEW_FILE,
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Found oversized or growing files in app/Repositories or app/Services:\n".
            implode("\n", $violations),
        );
    }

    public function test_baseline_files_still_exist(): void
    {
        $missing = [];

        foreach (array_keys(self::BASELINE_FILES) as $relativePath) {
            $absPath = self::APP_DIR.'/'.substr($relativePath, 4); // strip 'app/'
            if (! file_exists($absPath)) {
                $missing[] = $relativePath;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Baseline files were removed but not removed from BASELINE_FILES:\n".
            implode("\n", $missing)."\n\n".
            'Update the BASELINE_FILES constant in this test to remove stale entries.',
        );
    }

    public function test_no_stale_baseline_entries(): void
    {
        $stale = [];

        foreach (self::BASELINE_FILES as $relativePath => $baselineLines) {
            $absPath = self::APP_DIR.'/'.substr($relativePath, 4); // strip 'app/'
            if (! file_exists($absPath)) {
                continue; // handled by test_baseline_files_still_exist
            }

            $actualLines = $this->countLines($absPath);

            // If a baseline file no longer exceeds the threshold, it's stale
            if ($actualLines <= self::MAX_LINES_NEW_FILE) {
                $stale[] = sprintf(
                    '%s is %d lines (≤ %d threshold) — remove from BASELINE_FILES.',
                    $relativePath,
                    $actualLines,
                    self::MAX_LINES_NEW_FILE,
                );
            }
        }

        $this->assertSame(
            [],
            $stale,
            "Found stale baseline entries (files no longer exceed threshold):\n".
            implode("\n", $stale)."\n\n".
            'Remove these entries from BASELINE_FILES — the files are within limits now.',
        );
    }

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

    private function countLines(string $path): int
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return 0;
        }

        // Count newline characters — matches `wc -l` behaviour.
        // Files ending with a newline have N newlines for N lines.
        // Files not ending with a newline have N-1 newlines for N lines,
        // but we add 1 to account for the last line without a newline.
        $newlines = substr_count($content, "\n");

        return str_ends_with($content, "\n") ? $newlines : $newlines + 1;
    }

    private function countPublicMethods(string $path): int
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return 0;
        }

        return preg_match_all('/^\s*public\s+(?:static\s+)?function\s+/m', $content) ?: 0;
    }
}
