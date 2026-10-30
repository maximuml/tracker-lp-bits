<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $torrent
 * @property string $peer_id
 * @property string $ip
 * @property int $port
 * @property int $uploaded
 * @property int $downloaded
 * @property int $to_go
 * @property bool $seeder
 * @property string|null $started
 * @property string|null $last_action
 * @property string|null $prev_action
 * @property bool $connectable
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

use App\Enums\PeerConnectable;
use App\Enums\PeerSeeder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $counts
 */
class Peer extends NexusModel
{
    /** @var list<string> */
    protected $fillable = [
        'torrent', 'peer_id', 'ip', 'port', 'uploaded', 'downloaded', 'to_go', 'seeder', 'started', 'last_action',
        'prev_action', 'connectable', 'userid', 'agent', 'finishedat', 'downloadoffset', 'uploadedoffset',
        'ipv4', 'ipv6',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'started' => 'datetime',
        'last_action' => 'datetime',
        'prev_action' => 'datetime',
        'finishedat' => 'datetime:U',
        'connectable' => 'boolean',
        'seeder' => 'boolean',
    ];

    /** @var array<int|string, mixed> */
    public static $connectableText = [
        PeerConnectable::YES->value => 'Yes',
        PeerConnectable::NO->value => 'No',
    ];

    /** @return  mixed */
    public function getConnectableTextAttribute()
    {
        return self::$connectableText[$this->connectable] ?? '';
    }

    /** @return  bool */
    public function isSeeder()
    {
        return $this->seeder == PeerSeeder::YES->value;
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
