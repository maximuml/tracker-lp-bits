<?php

namespace App\Support;

/**
 * Per-request global key-value store.
 *
 * Replaces SupportContext::getGlobal()/setGlobal() with a container
 * singleton. Internally delegates to NexusContext which holds the
 * actual array storage, so getGlobalsForView() and other code that
 * reads from NexusContext::$globals continue to work.
 */
final class Globals
{
    /**
     * Get a global value by key, or default if not set.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return SupportContext::getContext()->getGlobal($key, $default);
    }

    /**
     * Set a global value.
     */
    public function set(string $key, mixed $value): void
    {
        SupportContext::getContext()->setGlobal($key, $value);
    }

    /**
     * Return a snapshot of the global state for Blade/PHP partials.
     *
     * @return array<string, mixed>
     */
    public function forView(): array
    {
        return SupportContext::getContext()->getGlobalsForView();
    }
}
