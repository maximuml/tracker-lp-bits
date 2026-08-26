<?php

namespace App\Support;

/**
 * Per-request language function/shoutbox arrays.
 *
 * Replaces SupportContext::getLangFunctions()/getLangShoutbox() with
 * a container singleton that reads from the global context (where the
 * language files are loaded by LegacyBootstrap and LegacyRequestMiddleware).
 */
final class Language
{
    /**
     * Get the legacy "functions.php" language array.
     *
     * @return array<string, string>
     */
    public function functions(): array
    {
        $lang = SupportContext::getGlobal('lang_functions');

        return is_array($lang) ? $lang : [];
    }

    /**
     * Get the legacy "shoutbox.php" language array.
     *
     * @return array<string, string>
     */
    public function shoutbox(): array
    {
        $lang = SupportContext::getGlobal('lang_shoutbox');

        return is_array($lang) ? $lang : [];
    }
}
