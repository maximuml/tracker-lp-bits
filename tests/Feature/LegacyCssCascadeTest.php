<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression for the legacy-chrome stylesheet cascade: theme.css used
 * to be emitted inside headStyles BEFORE css/modern.css, so the base
 * layer stomped the pack palette (Unshatter half-applied on legacy
 * pages), and nxt.css was linked twice. The pack theme now rides the
 * packThemeUrl slot after modern.css on every chrome variant.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class LegacyCssCascadeTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @param  list<string>  $needles
     * @return list<int> positions of the given needles inside $html
     */
    private function linkPositions(string $html, array $needles): array
    {
        $positions = [];
        foreach ($needles as $needle) {
            $pos = strpos($html, $needle);
            $this->assertNotFalse($pos, sprintf('stylesheet link "%s" missing from <head>', $needle));
            $positions[] = $pos;
        }

        return $positions;
    }

    public function test_legacy_page_loads_theme_after_modern_css(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['stylesheet' => 5]); // Unshatter

        $response = $this->withNexusCookie($user)->get('/web/faq');
        $response->assertOk();
        $html = (string) $response->getContent();

        [$sprites, $modern, $theme] = $this->linkPositions($html, [
            'styles/sprites.css',
            'css/modern.css',
            'styles/Unshatter/theme.css',
        ]);
        $this->assertLessThan($modern, $sprites, 'sprites.css must precede modern.css');
        $this->assertLessThan($theme, $modern, 'pack theme.css must load AFTER modern.css so its palette wins');
    }

    public function test_legacy_page_loads_classic_theme_after_modern_css(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['stylesheet' => 4]); // Classic

        $response = $this->withNexusCookie($user)->get('/web/faq');
        $response->assertOk();
        $html = (string) $response->getContent();

        [$modern, $theme] = $this->linkPositions($html, [
            'css/modern.css',
            'styles/Classic/theme.css',
        ]);
        $this->assertLessThan($theme, $modern, 'Classic theme.css must load AFTER modern.css on legacy chrome');
    }

    public function test_classic_theme_css_is_token_driven_with_dark_variant(): void
    {
        $css = file_get_contents(public_path('styles/Classic/theme.css'));
        $this->assertNotFalse($css);

        // Classic's light palette lives in --nxm-* overrides so the
        // modern.css token rules render the classic beige look.
        $this->assertStringContainsString('--nxm-surface: #ece9d8', $css);
        $this->assertStringContainsString('--nxm-bg: #cfcfcc', $css);

        // Dark variant: explicit data-theme plus auto via prefers-color-scheme.
        $this->assertMatchesRegularExpression(
            '/html\[data-theme="dark"\]:root\s*\{[^}]*--nxm-surface:/s',
            $css,
            'Classic must override --nxm-* tokens under data-theme="dark"',
        );
        $this->assertMatchesRegularExpression(
            '/@media \(prefers-color-scheme: dark\)[^}]*html\[data-theme="auto"\]:root\s*\{[^}]*--nxm-surface:/s',
            $css,
            'Classic must override --nxm-* tokens for auto + prefers-color-scheme: dark',
        );

        // Component rules must read vars, not the old hardcoded light palette.
        $this->assertStringNotContainsString('background-color: #ece9d8', $css);
        $this->assertStringNotContainsString('color: #000000;', $css);
        $this->assertStringContainsString('border: var(--nxm-border) 1px solid', $css);
    }

    public function test_nxt_css_is_linked_at_most_once(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['stylesheet' => 5]);

        $response = $this->withNexusCookie($user)->get('/web/faq');
        $response->assertOk();
        $html = (string) $response->getContent();

        $count = substr_count($html, 'nxt.css');
        $this->assertLessThanOrEqual(1, $count, 'nxt.css must not be linked twice in <head>');
        if (file_exists(public_path('css/nxt.css'))) {
            $this->assertSame(1, $count, 'nxt.css must be linked exactly once when built');
            // And it must come after the pack theme (utilities win the cascade).
            $theme = strpos($html, 'styles/Unshatter/theme.css');
            $nxt = strpos($html, 'nxt.css');
            $this->assertNotFalse($theme);
            $this->assertNotFalse($nxt);
            $this->assertLessThan($nxt, $theme, 'nxt.css must stay last in the cascade');
        }
    }
}
