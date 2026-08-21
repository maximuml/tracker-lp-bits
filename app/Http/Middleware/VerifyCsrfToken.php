<?php

namespace App\Http\Middleware;

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
        'adduser',
        'ajax',
        'attachment',
        'bans',
        'bitbucket-upload',
        'clearcache',
        'comment',
        'comment/*',
        'complains',
        'delete',
        'delacctadmin',
        'deletemessage',
        'donated',
        'downloadnotice',
        'faqactions',
        'fastdelete',
        'forums',
        'getusertorrentlistajax',
        'magic',
        'mailtest',
        'makepoll',
        'massmail',
        'maxlogin',
        'modrules',
        'modtask',
        'mybonus',
        'news',
        'nowarn',
        'offers',
        'polloverview',
        'preview',
        'report',
        'reports',
        'reset',
        'self-enable',
        'setlist_lookup',
        'settings',
        'shoutbox',
        'staffbox',
        'tags',
        'takeamountupload',
        'takeconfirm',
        'takecontact',
        'takeedit',
        'takeflush',
        'takeinvite',
        'takemessage',
        'takestaffmess',
        'takeupdate',
        'take-increment-bulk',
        'takereseed',
        'takeupload',
        'testip',
        'thanks',
        'usercp',
    ];

    /**
     * Determine if the request has a URI that should be excluded from CSRF.
     *
     * Excludes the dynamic passkey-login URI (configured via the
     * `login_secret` setting) so that external tools can POST to it
     * without a CSRF token.
     */
    protected function inExceptArray($request): bool
    {
        if (parent::inExceptArray($request)) {
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
