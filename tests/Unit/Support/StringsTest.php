<?php

namespace Tests\Unit\Support;

use App\Support\Strings;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
class StringsTest extends TestCase
{
    // ---------- pluralize() ----------

    public function test_pluralize_returns_singular_for_zero_or_one(): void
    {
        $this->assertSame('', Strings::pluralize(0, '', 's'));
        $this->assertSame('', Strings::pluralize(1, '', 's'));
        // Negative counts are also "singular" in the legacy contract
        // — the comparison is `> 1`, so -5 returns the singular form.
        $this->assertSame('', Strings::pluralize(-5, '', 's'));
    }

    public function test_pluralize_returns_plural_for_count_greater_than_one(): void
    {
        $this->assertSame('s', Strings::pluralize(2, '', 's'));
        $this->assertSame('s', Strings::pluralize(100, '', 's'));
    }

    public function test_pluralize_uses_strict_greater_than_threshold(): void
    {
        // Pinned legacy quirk: the threshold is `> 1`, not `>= 2`,
        // so fractional 1.5 already triggers the plural form. The
        // legacy `add_s()` is occasionally called with float ratios
        // (e.g. `add_s($mins)` where $mins is `floor(...) - hours*60`,
        // which is always integer in practice but the type isn't).
        $this->assertSame('s', Strings::pluralize(1.5, '', 's'));
        $this->assertSame('', Strings::pluralize(1.0, '', 's'));
    }

    public function test_pluralize_threads_language_aware_strings(): void
    {
        // The proxies for add_s() and is_or_are() pass language-aware
        // strings here. Verifies that we don't accidentally trim,
        // escape, or otherwise mutate the operator-supplied labels.
        $this->assertSame('are', Strings::pluralize(5, 'is', 'are'));
        $this->assertSame('is', Strings::pluralize(1, 'is', 'are'));
        $this->assertSame('ов', Strings::pluralize(5, 'а', 'ов'));
    }

    // ---------- randomCode() ----------

    public function test_random_code_length_matches_request(): void
    {
        $this->assertSame(0, strlen(Strings::randomCode(0)));
        $this->assertSame(1, strlen(Strings::randomCode(1)));
        $this->assertSame(6, strlen(Strings::randomCode(6)));
        $this->assertSame(32, strlen(Strings::randomCode(32)));
    }

    public function test_random_code_draws_only_from_unambiguous_alphabet(): void
    {
        // 21 chars: ABCDEFGH + PRMN + 1-9. NO 0, I, J, K, L, O, Q,
        // S, T, U, V, W, X, Y, Z, no lowercase. Pinned because a
        // future refactor that introduces lowercase or `0`/`O` would
        // silently break confirm-code readability for operators.
        $alphabet = 'ABCDEFGHPRMN123456789';
        $code = Strings::randomCode(200);
        $this->assertSame(200, strlen($code));
        for ($i = 0, $n = strlen($code); $i < $n; $i++) {
            $this->assertTrue(
                strpos($alphabet, $code[$i]) !== false,
                "Code contains disallowed character '{$code[$i]}' at index {$i}"
            );
        }
    }

    public function test_random_code_uses_csprng_not_deterministic(): void
    {
        // W1-10: randomCode() now uses random_int() (CSPRNG) instead of rand().
        // Two calls with the same seed should produce different results
        // because random_int() is not affected by srand().
        srand(12345);
        $a = Strings::randomCode(16);
        srand(12345);
        $b = Strings::randomCode(16);
        $this->assertNotSame($a, $b, 'randomCode() should use CSPRNG (random_int), not deterministic rand()');
    }

    // ---------- hidden() ----------

    // ---------- highlight ----------

    public function test_highlight_respects_custom_wrappers(): void
    {
        $this->assertSame(
            '<<HIT>>',
            Strings::highlight('HIT', '<HIT>', '<', '>')->toHtml(),
        );
    }

    public function test_strip_slashes_deep_leaves_unescaped_string_unchanged(): void
    {
        $this->assertSame('plain text', Strings::stripSlashesDeep('plain text'));
        $this->assertSame('', Strings::stripSlashesDeep(''));
    }

    public function test_strip_slashes_deep_walks_nested_arrays_preserving_keys(): void
    {
        $input = [
            'name' => "O\\'Brien",
            'nested' => [
                0 => 'a\\"b',
                'deep' => ['x' => "we\\'re"],
            ],
        ];
        $expected = [
            'name' => "O'Brien",
            'nested' => [
                0 => 'a"b',
                'deep' => ['x' => "we're"],
            ],
        ];

        $this->assertSame($expected, Strings::stripSlashesDeep($input));
    }

    public function test_strip_slashes_deep_returns_non_string_scalars_unchanged(): void
    {
        // Deliberate divergence from the legacy `stripslashes()` coercion:
        // non-string leaves (never produced by request data) pass through.
        $input = ['n' => 5, 'f' => 1.5, 'b' => true, 's' => "x\\'y"];
        $expected = ['n' => 5, 'f' => 1.5, 'b' => true, 's' => "x'y"];

        $this->assertSame($expected, Strings::stripSlashesDeep($input));
    }

    public function test_strip_slashes_deep_handles_empty_array(): void
    {
        $this->assertSame([], Strings::stripSlashesDeep([]));
    }

    // ---------- padHash() ----------

    public function test_pad_hash_pads_to_20_bytes(): void
    {
        $this->assertSame('12345678901234567890', Strings::padHash('12345678901234567890'));
        $this->assertSame('short               ', Strings::padHash('short'));
    }
}
