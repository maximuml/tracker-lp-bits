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
 * @property int $is_seed_box
 */
namespace App\Models;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $counts
 */
class Peer extends NexusModel
{
    protected $fillable = [
        'torrent', 'peer_id', 'ip', 'port', 'uploaded', 'downloaded', 'to_go', 'seeder', 'started', 'last_action',
        'prev_action', 'connectable', 'userid', 'agent', 'finishedat', 'downloadoffset', 'uploadedoffset', 'passkey',
        'ipv4', 'ipv6', 'is_seed_box'
    ];

    const CONNECTABLE_YES = 'yes';

    const CONNECTABLE_NO = 'no';

    protected $casts = [
        'started' => 'datetime',
        'last_action' => 'datetime',
        'prev_action' => 'datetime',
        'finishedat' => 'datetime:U',
    ];

    public static $connectableText = [
        self::CONNECTABLE_YES => '是',
        self::CONNECTABLE_NO => '否',
    ];

    const SEEDER_YES = 'yes';

    const SEEDER_NO = 'no';

    public static $cardTitles = [
        'upload_text' => '上传',
        'download_text' => '下载',
        'share_ratio' => '分享率',
        'agent_human' => '客户端',
        'connect_time_total' => '连接时间',
        'download_progress' => '完成进度',

    ];

    public function getConnectableTextAttribute()
    {
        return self::$connectableText[$this->connectable] ?? '';
    }

    public function scopeIsSeeder(Builder $builder)
    {
        return $builder->where('seeder', self::SEEDER_YES);
    }

    public function scopeIsNotSeeder(Builder $builder)
    {
        return $builder->where('seeder', self::SEEDER_NO);
    }

    public function isSeeder()
    {
        return $this->seeder == self::SEEDER_YES;
    }

    public function isNotSeeder()
    {
        return $this->seeder == self::SEEDER_NO;
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }

    public function relative_torrent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Torrent::class, 'torrent');
    }
}
