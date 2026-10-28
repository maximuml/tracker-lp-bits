<?php

namespace Tests\Unit\Support;

use App\Support\Email;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
class EmailTest extends TestCase
{
    // ---------- sanitizeForDisplay ----------

    public function test_sanitize_for_display_strips_angle_brackets(): void
    {
        $this->assertSame('user@example.com', Email::sanitizeForDisplay('<user@example.com>'));
        $this->assertSame('user@example.com', Email::sanitizeForDisplay('user@example.com<'));
        $this->assertSame('user@example.com', Email::sanitizeForDisplay('user@>example.com'));
    }

    public function test_sanitize_for_display_strips_legacy_magic_quotes_artefacts(): void
    {
        // Pinned legacy behaviour: the literal sequences `\'`, `\"`, `\\`
        // (a backslash followed by a quote / another backslash) are stripped.
        // These came from PHP's `magic_quotes_gpc` and cannot be auto-generated
        // any more on PHP 8+, but the strip is preserved defensively.
        $this->assertSame('useratexample.com', Email::sanitizeForDisplay("user\\'at\\\"example.com"));
        $this->assertSame('userexample.com', Email::sanitizeForDisplay('user\\\\example.com'));
    }

    public function test_sanitize_for_display_leaves_safe_characters_alone(): void
    {
        $this->assertSame(
            'first.last+tag@sub.example.co.uk',
            Email::sanitizeForDisplay('first.last+tag@sub.example.co.uk'),
        );
    }

    public function test_sanitize_for_display_passes_through_empty_string(): void
    {
        $this->assertSame('', Email::sanitizeForDisplay(''));
    }

    public function test_sanitize_for_display_does_not_validate(): void
    {
        // Pinned: strip-only, no rejection. The legacy contract is that
        // safe_email() returns whatever's left after the strip — even if
        // the result is no longer a valid address. check_email() is the
        // validator.
        $this->assertSame('not-an-email', Email::sanitizeForDisplay('not-an-email'));
        // All `<` and `>` are stripped; the result is the surrounding letters
        // with no separator — i.e. a still-invalid string, but the function
        // doesn't reject it because validation is `check_email()`'s job.
        $this->assertSame('onlychars', Email::sanitizeForDisplay('only<><><<><>chars'));
    }

    // ---------- charsetFor ----------

    public function test_charset_for_chinese_folders_returns_gbk(): void
    {
        $this->assertSame('gbk', Email::charsetFor('chs'));
        $this->assertSame('gbk', Email::charsetFor('cht'));
    }

    public function test_charset_for_other_folders_falls_back_to_utf8(): void
    {
        $this->assertSame('utf-8', Email::charsetFor('eng'));
        $this->assertSame('utf-8', Email::charsetFor('jpn'));
        $this->assertSame('utf-8', Email::charsetFor('rus'));
        $this->assertSame('utf-8', Email::charsetFor(''));
    }

    public function test_charset_for_is_strictly_case_sensitive(): void
    {
        // Pinned legacy behaviour: `$lang == 'chs'` is a strict string
        // equality check after lookup, so capitalised forms fall back
        // to utf-8. This is intentional — the language-folder cookie
        // is always set in lowercase by the legacy bootstrap.
        $this->assertSame('utf-8', Email::charsetFor('CHS'));
        $this->assertSame('utf-8', Email::charsetFor('Cht'));
        $this->assertSame('utf-8', Email::charsetFor('chS'));
    }
}
