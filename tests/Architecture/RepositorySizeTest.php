<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * W0-04: Ratchet on large files in app/Repositories and app/Services.
 *
 * God-object repositories are a recognised tech-debt hotspot. This test
 * establishes a baseline of files exceeding 500 lines and fails if:
 *   - a new file exceeds 500 lines, or
 *   - an existing baseline file grows beyond its recorded size.
 *
 * Baseline captured on 2026-09-06 (post-T-23).
 *
 * To update after a legitimate split (e.g. extracting a service from a
 * repository), remove the entry from BASELINE_FILES and commit.
 */
final class RepositorySizeTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    /** Maximum lines for any new file in Repositories or Services. */
    private const MAX_LINES_NEW_FILE = 500;

    /**
     * Baseline: files that already exceed MAX_LINES_NEW_FILE at capture time.
     * Map of relative path => line count.
     *
     * @var array<string, int>
     */
    private const BASELINE_FILES = [
        // Repositories (14 files > 500 lines)
        'app/Repositories/TorrentSearchRepository.php' => 961,
        'app/Repositories/TorrentRepository.php' => 845,
        'app/Repositories/ForumRepository.php' => 812,
        'app/Repositories/UserRepository.php' => 745,
        'app/Repositories/UserModerationRepository.php' => 731,
        'app/Repositories/MeiliSearchRepository.php' => 725,
        'app/Repositories/UploadRepository.php' => 722,
        'app/Repositories/ToolRepository.php' => 717,
        'app/Repositories/HitAndRunRepository.php' => 693,
        'app/Repositories/UsercpRepository.php' => 645,
        'app/Repositories/BonusRepository.php' => 585,
        'app/Repositories/ExamRepository.php' => 562,
        'app/Repositories/SearchBoxRepository.php' => 526,
        'app/Repositories/ToptenRepository.php' => 525,
        // Services (6 files > 500 lines)
        'app/Services/RegistrationService.php' => 600,
        'app/Services/OfferPageService.php' => 588,
        'app/Services/ForumService.php' => 558,
        'app/Services/UsercpPageService.php' => 529,
        'app/Services/IndexPageService.php' => 525,
        'app/Services/MessageService.php' => 514,
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
}
