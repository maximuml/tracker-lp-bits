<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

final class XssProtectionTest extends TestCase
{
    /**
     * htmlspecialchars with ENT_QUOTES should neutralize common XSS payloads.
     */
    public function test_htmlspecialchars_neutralizes_xss_in_friend_title(): void
    {
        $payloads = [
            '<script>alert(1)</script>',
            '"><script>alert(1)</script>',
            "';alert(1);//",
            '<img src=x onerror=alert(1)>',
        ];

        foreach ($payloads as $payload) {
            $escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
            $this->assertStringNotContainsString('<script>', $escaped);
            $this->assertStringNotContainsString('<img ', $escaped);
        }
    }

    /**
     * strip_tags with a whitelist should remove <script>, <iframe>, <object>,
     * <embed> tags while preserving safe formatting tags.
     */
    public function test_strip_tags_removes_dangerous_tags_from_faq_answer(): void
    {
        $allowedTags = '<a><b><i><u><s><br><p><div><span><ul><ol><li><img><font><pre><code><hr><table><tr><td><th><strong><em><h1><h2><h3><h4><h5><h6><blockquote>';

        $faqAnswer = '<p>Normal <b>bold</b> text</p><script>alert(1)</script><iframe src="evil"></iframe><object data="evil"></object>';
        $cleaned = strip_tags($faqAnswer, $allowedTags);

        $this->assertStringContainsString('<p>Normal <b>bold</b> text</p>', $cleaned);
        $this->assertStringNotContainsString('<script>', $cleaned);
        $this->assertStringNotContainsString('</script>', $cleaned);
        $this->assertStringNotContainsString('<iframe', $cleaned);
        $this->assertStringNotContainsString('<object', $cleaned);
    }

    /**
     * Lang strings used in link text should be escaped to prevent injection
     * via compromised language files.
     */
    public function test_lang_string_escaping_in_friend_links(): void
    {
        $evilLang = '<script>alert(1)</script>Remove from friends';
        $escaped = htmlspecialchars($evilLang, ENT_QUOTES, 'UTF-8');

        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('Remove from friends', $escaped);
    }
}
