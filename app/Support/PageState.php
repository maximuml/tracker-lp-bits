<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Typed per-request page state shared between legacy bootstrap, layout
 * builders and the view composer (language folder, pagination key
 * shortcuts, rendered menu, settings exported to Blade).
 *
 * Backed by NexusContext, so it is reset together with the rest of the
 * legacy request state ({@see SupportContext::reset()}) and cannot leak
 * between requests in a long-lived worker.
 */
final class PageState
{
    public static function instance(): self
    {
        return new self;
    }

    public function langDir(string $default = ''): string
    {
        $dir = SupportContext::getContext()->langDir;

        return $dir !== '' ? $dir : $default;
    }

    public function setLangDir(string $dir): void
    {
        SupportContext::getContext()->langDir = $dir;
    }

    public function keyShortcutScript(): string
    {
        return SupportContext::getContext()->keyShortcutScript;
    }

    public function setKeyShortcutScript(string $script): void
    {
        SupportContext::getContext()->keyShortcutScript = $script;
    }

    public function menuHtml(): string
    {
        return SupportContext::getContext()->menuHtml;
    }

    public function menuSelected(): string
    {
        return SupportContext::getContext()->menuSelected;
    }

    public function setMenu(string $html, string $selected): void
    {
        $context = SupportContext::getContext();
        $context->menuHtml = $html;
        $context->menuSelected = $selected;
    }

    /**
     * @param  callable(): array<string, mixed>  $resolve
     * @return array<string, mixed>
     */
    public function viewSettings(callable $resolve): array
    {
        $context = SupportContext::getContext();

        return $context->viewSettings ??= $resolve();
    }
}
