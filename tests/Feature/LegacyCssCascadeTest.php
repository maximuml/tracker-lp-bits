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

    public function test_classic_theme_css_shares_the_modern_palette(): void
    {
        $css = file_get_contents(public_path('styles/Classic/theme.css'));
        $this->assertNotFalse($css);

        // Classic no longer owns the palette: modern.css --nxm-* tokens
        // set the scheme so boxed pages match modern pages. The pack
        // file only carries --classic-* accents and color-scheme hints.
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*--nxm-[a-z-]+\s*:/m',
            $css,
            'Classic must not override --nxm-* palette tokens',
        );
        $this->assertMatchesRegularExpression(
            '/html\[data-theme="dark"\]:root\s*\{[^}]*--classic-/s',
            $css,
            'Classic must keep pack accents under data-theme="dark"',
        );
        $this->assertMatchesRegularExpression(
            '/@media \(prefers-color-scheme: dark\)[^}]*html\[data-theme="auto"\]:root\s*\{[^}]*color-scheme: dark/s',
            $css,
            'Classic must keep the auto + prefers-color-scheme dark block',
        );

        // Global element/type styling must not fight the modern cascade:
        // body, bare a:link, li and the palette .colhead all come from
        // modern.css so typography is identical on every chrome variant.
        $this->assertDoesNotMatchRegularExpression('/^body[\s{]/m', $css);
        $this->assertDoesNotMatchRegularExpression('/^a:link/m', $css);
        $this->assertDoesNotMatchRegularExpression('/^li[\s{]/m', $css);
        $this->assertStringNotContainsString('.colhead, .nx-colhead', $css);

        // Shared legacy classes moved to modern.css so modern-variant
        // pages style them too and boxed pages share the same look.
        $modern = file_get_contents(public_path('css/modern.css'));
        $this->assertNotFalse($modern);
        foreach (['.codetop', '.codemain', 'ul.menu', 'input.btn2', '.dropmenu'] as $needle) {
            $this->assertStringContainsString($needle, $modern, sprintf('%s missing from modern.css', $needle));
            $this->assertStringNotContainsString($needle, $css, sprintf('%s must not be re-overridden in Classic', $needle));
        }

        // Remaining component rules still read vars, not hardcoded colors.
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
