<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Value Object representing sanitized HTML.
 *
 * Created by {@see SafeHtml::make()}. The wrapped string is guaranteed
 * to have passed through the Symfony HtmlSanitizer and is safe to render
 * via `{!! !!}` in Blade templates.
 *
 * @see SafeHtml
 */
final class SanitizedHtml
{
    public function __construct(
        private readonly string $html,
    ) {}

    public function __toString(): string
    {
        return $this->html;
    }

    public function value(): string
    {
        return $this->html;
    }
}
