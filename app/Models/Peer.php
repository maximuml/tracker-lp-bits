<?php

/**
 * @property int $id
 * @property int $torrent
 * @property string $peer_id
 * @property string $ip
 * @property int $port
 * @property int $uploaded
 * @property int $downloaded
 * @property int $to_go
 * @property string $seeder
 * @property string|null $started
 * @property string|null $last_action
 * @property string|null $prev_action
 * @property string $connectable
 * @property int $userid
 * @property string $agent
 * @property int $finishedat
 * @property int $downloadoffset
 * @property int $uploadoffset
 * @property string $passkey
 * @property string $ipv4
 * @property string $ipv6
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $counts
 */
class Peer extends NexusModel
{
    /** @var list<string> */
    protected $fillable = [
        'torrent', 'peer_id', 'ip', 'port', 'uploaded', 'downloaded', 'to_go', 'seeder', 'started', 'last_action',
        'prev_action', 'connectable', 'userid', 'agent', 'finishedat', 'downloadoffset', 'uploadedoffset', 'passkey',
        'ipv4', 'ipv6',
    ];

    const CONNECTABLE_YES = 'yes';

    const CONNECTABLE_NO = 'no';

    /** @var array<string, string> */
    protected $casts = [
        'started' => 'datetime',
        'last_action' => 'datetime',
        'prev_action' => 'datetime',
        'finishedat' => 'datetime:U',
    ];

    /** @var array<int|string, mixed> */
    public static $connectableText = [
        self::CONNECTABLE_YES => '是',
        self::CONNECTABLE_NO => '否',
    ];

    const SEEDER_YES = 'yes';

    const SEEDER_NO = 'no';

    /** @var array<int|string, mixed> */
    public static $cardTitles = [
        'upload_text' => '上传',
        'download_text' => '下载',
        'share_ratio' => '分享率',
        'agent_human' => '客户端',
        'connect_time_total' => '连接时间',
        'download_progress' => '完成进度',

    ];

    /** @return  mixed */
    public function getConnectableTextAttribute()
    {
        return self::$connectableText[$this->connectable] ?? '';
    }

    /**
     * @param  Builder<Peer>  $builder
     * @return mixed
     */
    public function scopeIsSeeder(Builder $builder)
    {
        return $builder->where('seeder', self::SEEDER_YES);
    }

    /**
     * @param  Builder<Peer>  $builder
     * @return mixed
     */
    public function scopeIsNotSeeder(Builder $builder)
    {
        return $builder->where('seeder', self::SEEDER_NO);
    }

    /** @return  mixed */
    public function isSeeder()
    {
        return $this->seeder == self::SEEDER_YES;
    }

    /** @return  mixed */
    public function isNotSeeder()
    {
        return $this->seeder == self::SEEDER_NO;
    }

    /** @return  BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }

    /** @return  BelongsTo<Torrent, $this> */
    public function relative_torrent(): BelongsTo
    {
        return $this->belongsTo(Torrent::class, 'torrent');
    }
}
