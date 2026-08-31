<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string|null $added
 * @property string $txt
 * @property string $security_level
 * @property int $uid
 */

namespace App\Models;

class SiteLog extends NexusModel
{
    /** @var string */
    protected $table = 'sitelog';

    /** @var list<string> */
    protected $fillable = ['added', 'txt', 'security_level', 'uid'];

    /** @var array<string, string> */
    protected $casts = [
        'added' => 'datetime',
        'uid' => 'integer',
    ];

    /**
     * @param  mixed  $uid
     * @param  mixed  $content
     * @param  mixed  $isMod
     */
    public static function add($uid, $content, $isMod = false): void
    {
        self::query()->insert([
            'uid' => $uid,
            'txt' => $content,
            'security_level' => $isMod ? 'mod' : 'normal',
            'added' => now(),
        ]);
    }
}
