<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserAppendPromotion;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression test for the Stage-0 escaped-markup bug class:
 * HTML-producing helpers/lang values rendered through `{{ }}`
 * leaked literal `&lt;span`, `&lt;b&gt;`, `&amp;nbsp;` text into
 * pages. No existing test caught it because the suites assert on
 * data, not on raw-entity leakage.
 *
 * These assertions walk the pages touched by the hotfix and forbid
 * entity-encoded tag text in the response body.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class NoLeakedMarkupTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Entity-encoded tags that signal markup was escaped into text
     * instead of rendered. `&lt;` alone is legal (plain-text "<"),
     * so the patterns pin tag names.
     *
     * @var list<string>
     */
    private const LEAK_PATTERNS = [
        '&lt;span',
        '&lt;time',
        '&lt;b&gt;',
        '&lt;script',
        '&lt;font',
        '&lt;br',
        '&amp;nbsp;',
    ];

    /** @return array<string, array{0: string}> */
    public static function pageProvider(): array
    {
        return [
            'index' => ['/web/index'],
            'torrents' => ['/web/torrents'],
            'forums' => ['/forums'],
            'usercp' => ['/usercp'],
        ];
    }

    #[DataProvider('pageProvider')]
    public function test_page_renders_no_escaped_markup_text(string $path): void
    {
        $user = User::factory()->create();

        $response = $this->withNexusCookie($user)->get($path);

        $response->assertOk();
        $html = (string) $response->getContent();

        foreach (self::LEAK_PATTERNS as $pattern) {
            $this->assertStringNotContainsString(
                $pattern,
                $html,
                "$path leaks entity-encoded markup ($pattern) — a HTML-producing value was escaped via {{ }}",
            );
        }
    }

    public function test_topten_renders_no_escaped_markup_text(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->withNexusCookie($user)->get('/web/topten');

        $response->assertOk();
        $html = (string) $response->getContent();

        foreach (self::LEAK_PATTERNS as $pattern) {
            $this->assertStringNotContainsString(
                $pattern,
                $html,
                "/topten leaks entity-encoded markup ($pattern) — a HTML-producing value was escaped via {{ }}",
            );
        }
    }

    public function test_torrent_list_renders_promotion_legend_as_markup(): void
    {
        $user = User::factory()->create(['appendpromotion' => UserAppendPromotion::HIGHLIGHT->value]);
        // The default browse mode only lists categories from its searchbox
        // catlist (401-409); the factory's random category ID would be
        // filtered out and the empty list never reaches the legend.
        Torrent::factory()->create(['category' => 401]);

        $response = $this->withNexusCookie($user)->get('/web/torrents');

        $response->assertOk();
        $html = (string) $response->getContent();

        // The promotion legend is a markup-bearing lang value rendered
        // through SafeHtml — its links must be real tags.
        $this->assertStringContainsString('Those highlighted are', $html);
        $this->assertStringContainsString('<a href="/web/torrents?spstate=2"', $html);
        $this->assertStringNotContainsString('&lt;a href', $html);
    }
}
