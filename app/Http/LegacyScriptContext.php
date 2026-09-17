<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Globals;
use App\Support\LegacyAuth;

/**
 * Load per-script legacy language files and run the parked() guard.
 *
 * Extracted from LegacyRequestMiddleware so the middleware can focus
 * on request bootstrap orchestration.
 */
final class LegacyScriptContext
{
    public function __construct(
        private readonly Globals $globals,
    ) {}

    /** @var array<string, string|array<int, string>> */
    private const EXTRA_LANG_FILES = [
        'search' => ['torrents.php'],
        'shoutbox_history' => ['shoutbox.php'],
        'take-increment-bulk' => ['increment-bulk.php'],
        'upload' => ['edit.php'],
        'my_bonus' => ['mybonus.php'],
    ];

    /** @var array<int, string> */
    private const PARKED_SCRIPTS = [
        'viewsnatches', 'users', 'forums', 'report', 'cheaterbox', 'upload',
        'offers', 'comment', 'userdetails', 'checkuser', 'takeconfirm', 'invite', 'bitbucket-upload',
        'mybonus', 'userhistory', 'moresmilies', 'torrents', 'getattachment',
        'sendmessage', 'reports', 'self-enable', 'friends', 'settings', 'topten', 'attendance',
        'donorlist', 'warned', 'nowarn', 'bans', 'cheaterbox', 'cheaters', 'iphistory', 'ipcheck', 'ipsearch',
        'staffbox', 'stats', 'allagents',
        'delacctadmin', 'deletedisabled', 'massmail', 'maxlogin',
        'catmanage', 'forummanage', 'moforums', 'fields', 'formats', 'videoformats',
    ];

    public function boot(string $script, string $rootpath): void
    {
        $this->loadLanguage($script, $rootpath);

        if (in_array($script, self::PARKED_SCRIPTS, true)) {
            LegacyAuth::parkedFromContext();
        }
    }

    private function loadLanguage(string $script, string $rootpath): void
    {
        $scriptLangFiles = array_unique(array_merge(
            [$script.'.php'],
            self::EXTRA_LANG_FILES[$script] ?? []
        ));

        // lang/en/lang_<script>.php is gone — the same arrays now live in
        // resources/lang/en/legacy/<var>.php and resolve via the translator.
        // The variable name was the filename with dashes stripped.
        foreach ($scriptLangFiles as $scriptLangFile) {
            $suffix = str_replace('-', '', basename($scriptLangFile, '.php'));
            $lines = trans('legacy/'.$suffix);
            $this->globals->set('lang_'.$suffix, is_array($lines) ? $lines : []);
        }
    }
}
