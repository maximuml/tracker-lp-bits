<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    const TG_WEBHOOK_PREFIX = "tg-webhook";
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        self::TG_WEBHOOK_PREFIX . "/*",
        "web/token/*",
        "takeupload",
        "takeedit",
        "bitbucket-upload",
        "offers",
        "usercp",
        "comment",
        "comment/*",
        "mybonus",
        "news",
        "makepoll",
        "polloverview",
        "attendance",
        "takemessage",
        "deletemessage",
        "report",
        "reports",
        "bans",
        "cheaterbox",
        "cheaters",
        "iphistory",
        "ipcheck",
        "ipsearch",
        "modtask",
        "staffbox",
        "takestaffmess",
        "takecontact",
        "modrules",
        "nowarn",
        "takeconfirm",
        "fastdelete",
        "takeflush",
        "takereseed",
        "clearcache",
        "donated",
        "faqactions",
        "ajax",
        "getusertorrentlistajax",
        "catmanage",
        "forummanage",
        "moforums",
        "fields",
        "shoutbox",
        "attachment",
    ];
}
