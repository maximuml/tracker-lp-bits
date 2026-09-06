<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\Globals;
use App\Support\LegacyAuth;
use App\Support\Locale;

/**
 * Load per-script legacy language files and run the parked() guard.
 *
 * Extracted from LegacyRequestMiddleware so the middleware can focus
 * on request bootstrap orchestration.
 */
final class LegacyScriptContext
{
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

        foreach ($scriptLangFiles as $scriptLangFile) {
            $langPath = $rootpath.Locale::scriptFilePath((string) $scriptLangFile, (bool) false, (string) '');
            if (! is_file($langPath)) {
                continue;
            }

            $SITENAME = app(Globals::class)->get('SITENAME');
            $SITEEMAIL = app(Globals::class)->get('SITEEMAIL');
            $REPORTMAIL = app(Globals::class)->get('REPORTMAIL');
            $BASEURL = app(Globals::class)->get('BASEURL');
            $before = get_defined_vars();
            require $langPath;
            foreach (array_diff_key(get_defined_vars(), $before) as $langKey => $langValue) {
                if (in_array($langKey, ['before', 'path', 'langPath', 'scriptLangFiles', 'rootpath', 'scriptLangFile'], true)) {
                    continue;
                }
                app(Globals::class)->set($langKey, $langValue);
            }
        }
    }
}
