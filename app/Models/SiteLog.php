<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property string|null $added
 * @property string $txt
 * @property SitelogSecurityLevel $security_level
 * @property int $uid
 */

namespace App\Models;

use App\Enums\SitelogSecurityLevel;

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
        'security_level' => SitelogSecurityLevel::class,
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
            'security_level' => $isMod ? SitelogSecurityLevel::MOD->value : SitelogSecurityLevel::NORMAL->value,
            'added' => now(),
        ]);
    }
}
