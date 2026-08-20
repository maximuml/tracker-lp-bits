<?php

namespace App\Http\Middleware;

use App\Support\Config\SiteConfig;
use App\Support\Environment;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    const TG_WEBHOOK_PREFIX = 'tg-webhook';

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        self::TG_WEBHOOK_PREFIX.'/*',
        'web/token/*',
        'takeupload',
        'takeedit',
        'bitbucket-upload',
        'offers',
        'usercp',
        'comment',
        'comment/*',
        'mybonus',
        'news',
        'makepoll',
        'polloverview',
        'attendance',
        'takemessage',
        'deletemessage',
        'report',
        'reports',
        'modtask',
        'takestaffmess',
        'takecontact',
        'modrules',
        'fastdelete',
        'takeflush',
        'takereseed',
        'clearcache',
        'donated',
        'faqactions',
        'ajax',
        'getusertorrentlistajax',
        'shoutbox',
        'forums',
        'attachment',
        'settings',
        'magic',
        'takeamountupload',
        'takeinvite',
        'takeupdate',
        'tags',
        'preview',
        'mailtest',
        'reset',
        'self-enable',
        'adduser',
        'complains',
        'delete',
        'downloadnotice',
        'setlist_lookup',
        'take-increment-bulk',
        'thanks',
        'testip',
    ];

    /**
     * Determine if the request should pass CSRF verification.
     *
     * Excludes the dynamic passkey-login URI (configured via the
     * `login_secret` setting) so that external tools can POST to it
     * without a CSRF token.
     */
    protected function shouldPassThrough($request): bool
    {
        if (parent::shouldPassThrough($request)) {
            return true;
        }

        if (! Environment::isConsole()) {
            $passkeyLoginUri = SiteConfig::current()->security->loginSecret();
            if (! empty($passkeyLoginUri) && $request->is($passkeyLoginUri)) {
                return true;
            }
        }

        return false;
    }
}
