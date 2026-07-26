<?php

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
    protected $table = 'sitelog';

    protected $fillable = ['added', 'txt', 'security_level', 'uid'];

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
