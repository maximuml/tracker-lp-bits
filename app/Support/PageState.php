<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Typed per-request page state shared between legacy bootstrap, layout
 * builders and the view composer (language folder, pagination key
 * shortcuts, rendered menu, settings exported to Blade).
 *
 * Backed by NexusContext, so it is reset together with the rest of the
 * legacy request state ({@see NexusContext::reset()}) and cannot leak
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
        $dir = NexusContext::instance()->langDir;

        return $dir !== '' ? $dir : $default;
    }

    public function setLangDir(string $dir): void
    {
        NexusContext::instance()->langDir = $dir;
    }

    public function keyShortcutScript(): string
    {
        return NexusContext::instance()->keyShortcutScript;
    }

    public function setKeyShortcutScript(string $script): void
    {
        NexusContext::instance()->keyShortcutScript = $script;
    }

    public function menuHtml(): string
    {
        return NexusContext::instance()->menuHtml;
    }

    public function menuSelected(): string
    {
        return NexusContext::instance()->menuSelected;
    }

    public function setMenu(string $html, string $selected): void
    {
        $context = NexusContext::instance();
        $context->menuHtml = $html;
        $context->menuSelected = $selected;
    }

    /**
     * @param  callable(): array<string, mixed>  $resolve
     * @return array<string, mixed>
     */
    public function viewSettings(callable $resolve): array
    {
        $context = NexusContext::instance();

        return $context->viewSettings ??= $resolve();
    }
}
