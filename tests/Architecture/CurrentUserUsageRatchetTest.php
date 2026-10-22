<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Phase 3.4 ratchet: lines referencing `CurrentUser` in app/ must not grow.
 *
 * `ActorContext` is the typed, per-request replacement for the legacy
 * `CurrentUser` singleton / `$CURUSER` array pattern. Migrations reduce
 * this count; new code should consume `ActorContext` (constructor or
 * method injection) instead of reaching for the raw user array.
 *
 * Excluded: `app/Support/CurrentUser.php` (the class being migrated away
 * from) and `app/DTOs/Auth/ActorContext.php` (the adapter that still
 * bridges to it while the migration is in progress).
 *
 * Baseline captured after the OfferVoteService/OfferVote migration: 247
 * lines; lowered to 69 after the Phase 4.1 batch migration of services,
 * repositories, controllers and Livewire components, then 71 when
 * `BasePageController::renderPage` reverted to `CurrentUser::instance()`
 * (base-class auth gate — 37 children override `__construct` without
 * calling parent, and service-location is ratcheted to zero in
 * controllers), then 67 after the two pure-reader ViewModel factories
 * moved to `ActorContext`. The remainder is the deliberate auth/legacy
 * seam (Support/*, middleware, guards, `BasePageController`, and
 * `set()`/`reset()` mutation sites). Lower it as more surfaces
 * migrate; never raise it. 32 after all Support ambient reads and
 * `SharedViewVariables` moved to `ActorContext` — only the fromAuth
 * bridge, cookie-auth `set()` writes, controller `promote*()`
 * promotions, and imports of the write/lifecycle seam remain.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class CurrentUserUsageRatchetTest extends TestCase
{
    private const APP_DIR = __DIR__.'/../../app';

    private const EXCLUDED = [
        'Support/CurrentUser.php',
        'DTOs/Auth/ActorContext.php',
    ];

    private const BASELINE = 32;

    public function test_current_user_references_do_not_exceed_baseline(): void
    {
        $count = $this->countCurrentUserLines();

        $this->assertLessThanOrEqual(
            self::BASELINE,
            $count,
            sprintf(
                'CurrentUser reference lines in app/ increased from baseline %d to %d. '
               .'Consume App\DTOs\Auth\ActorContext instead of the CurrentUser singleton. '
               .'Lower the baseline as migrations proceed; never raise it.',
                self::BASELINE,
                $count,
            ),
        );
    }

    private function countCurrentUserLines(): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::APP_DIR, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace(self::APP_DIR.'/', '', $file->getPathname());
            if (in_array($relativePath, self::EXCLUDED, true)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            foreach (explode("\n", $content) as $line) {
                if (preg_match('/\bCurrentUser\b/', $line)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
