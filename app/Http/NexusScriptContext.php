<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\AccessGate;
use App\Auth\AuthContext;

/**
 * Load per-script legacy language files and run the parked() guard.
 *
 * Extracted from NexusRequestMiddleware so the middleware can focus
 * on request bootstrap orchestration.
 */
final class NexusScriptContext
{
    public function __construct(
        private readonly AccessGate $accessGate,
    ) {}

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
        if (in_array($script, self::PARKED_SCRIPTS, true)) {
            $this->accessGate->parked(AuthContext::current());
        }
    }
}
