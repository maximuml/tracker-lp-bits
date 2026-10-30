<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Ratchet on the per-request state-holder convention.
 *
 * All mutable per-request state lives in App\Support\State\* — `final`
 * classes carrying the container `#[Scoped]` attribute and implementing
 * RequestScopedState (`reset(): void`). ResetNexus drops every scoped
 * instance via forgetScopedInstances() between requests, queue jobs and
 * worker cycles, so long-lived workers cannot leak one request's
 * permissions/settings/caches into the next.
 *
 * This test fails when a holder in app/Support/State:
 *   - is not `final`
 *   - is missing `#[Scoped]`
 *   - does not implement RequestScopedState
 *   - loses its `public function reset(): void`
 *   - declares any `static` property (mutable or not — state belongs on
 *     the scoped instance; `instance()` is the only permitted static,
 *     and it is a method)
 *
 * To fix a failure: conform the holder to the convention, or move the
 * state somewhere justified and documented — do not silence the check.
 */
#[TestCategory(TestCategory::ARCHITECTURE)]
final class RequestScopedStateRatchetTest extends TestCase
{
    private const STATE_DIR = __DIR__.'/../../app/Support/State';

    public function test_every_state_holder_follows_the_scoped_convention(): void
    {
        $violations = [];

        foreach ($this->stateClasses() as $file => $content) {
            $name = basename($file, '.php');
            $problems = [];

            if (! str_contains($content, '#[Scoped]')) {
                $problems[] = 'missing #[Scoped] attribute';
            }
            if (! preg_match('/^final class\s+\w+/m', $content)) {
                $problems[] = 'class is not final';
            }
            if (! str_contains($content, 'implements RequestScopedState')) {
                $problems[] = 'does not implement RequestScopedState';
            }
            if (! preg_match('/public function reset\(\): void/', $content)) {
                $problems[] = 'missing public function reset(): void';
            }
            if (preg_match('/^\s*(?:public|private|protected)\s+static\s+(?:[\w\\\\|?]+\s+)?\$/m', $content)) {
                $problems[] = 'declares a static property — state belongs on the scoped instance';
            }

            if ($problems !== []) {
                $violations[] = $name.' ('.implode('; ', $problems).')';
            }
        }

        $this->assertSame(
            [],
            $violations,
            'State holders in app/Support/State violate the request-scoped '
           .'convention: '.implode(', ', $violations),
        );
    }

    public function test_state_directory_only_contains_scoped_holders(): void
    {
        $names = [];
        foreach ($this->stateClasses() as $file => $content) {
            $names[] = basename($file, '.php');
        }

        $this->assertNotEmpty($names, 'No state holders found — did the directory move?');
        foreach ($names as $name) {
            $this->assertStringEndsWith(
                'State',
                $name,
                "app/Support/State/{$name}.php does not follow the *State naming convention",
            );
        }
    }

    /** @return array<string, string> file path => content */
    private function stateClasses(): array
    {
        $files = [];
        foreach (glob(self::STATE_DIR.'/*.php') ?: [] as $file) {
            $name = basename($file);
            if ($name === 'RequestScopedState.php') {
                continue;
            }
            $content = file_get_contents($file);
            if ($content !== false) {
                $files[$file] = $content;
            }
        }

        return $files;
    }
}
