<?php

declare(strict_types=1);

namespace App\Support\Html;

/**
 * SafeHtml value object.
 *
 * Wraps an HTML string that has been sanitized and is safe to render
 * in a Blade template via `{!! !!}`. The string cannot be accessed
 * accidentally as a plain string — callers must explicitly call
 * `toHtml()` or use the `BladeSafeHtml` Blade directive.
 *
 * This creates a type boundary between "untrusted string" and
 * "HTML that has been sanitized", preventing accidental raw output
 * of user-controlled data.
 */
final class SafeHtml
{
    private string $html;

    private function __construct(string $html)
    {
        $this->html = $html;
    }

    /**
     * Create a SafeHtml instance from already-sanitized HTML.
     *
     * Use this when the HTML has been produced by a trusted source
     * (e.g. BBCode renderer, framework-generated markup, or a
     * hardcoded template).
     */
    public static function fromTrustedHtml(string $html): self
    {
        return new self($html);
    }

    /**
     * Create a SafeHtml instance by sanitizing untrusted HTML.
     *
     * Uses the HtmlSanitizer to strip dangerous tags, attributes,
     * and URL schemes while preserving safe formatting.
     */
    public static function fromUntrustedHtml(string $html): self
    {
        return new self(HtmlSanitizer::sanitize($html));
    }

    /**
     * Create a SafeHtml instance from plain text (not HTML).
     *
     * The text is escaped via htmlspecialchars so it is safe to
     * render in an HTML context.
     */
    public static function fromPlainText(string $text): self
    {
        return new self(htmlspecialchars($text, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8'));
    }

    /**
     * Return the sanitized HTML string.
     */
    public function toHtml(): string
    {
        return $this->html;
    }

    /**
     * Implicit conversion to string.
     */
    public function __toString(): string
    {
        return $this->html;
    }

    /**
     * Check if the HTML is empty.
     */
    public function isEmpty(): bool
    {
        return $this->html === '';
    }

    /**
     * Concatenate with another SafeHtml instance.
     */
    public function append(self $other): self
    {
        return new self($this->html.$other->html);
    }
}
