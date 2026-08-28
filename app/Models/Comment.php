<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $user
 * @property int $torrent
 * @property string|null $added
 * @property string|null $text
 * @property string|null $ori_text
 * @property int $editedby
 * @property string|null $editdate
 * @property int $offer
 * @property int $request
 * @property string $anonymous
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends NexusModel
{
    /** @var array<string, string> */
    protected $casts = [
        'added' => 'datetime',
        'editdate' => 'datetime',
    ];

    /** @var list<string> */
    protected $fillable = ['user', 'torrent', 'added', 'text', 'ori_text', 'editedby', 'editdate', 'offer', 'anonymous'];

    const TYPE_TORRENT = 'torrent';

    const TYPE_OFFER = 'offer';

    const TYPE_MAPS = [
        self::TYPE_TORRENT => [
            'model' => Torrent::class,
            'foreign_key' => 'torrent',
            'target_name_field' => 'name',
            'target_script' => 'details.php?id=%s',
        ],
        self::TYPE_OFFER => [
            'model' => Offer::class,
            'foreign_key' => 'offer',
            'target_name_field' => 'name',
            'target_script' => 'offers.php?id=%s&off_details=1',
        ],
    ];

    /**
     * @param  Builder<Comment>  $query
     * @return mixed
     */
    public function scopeType(Builder $query, string $type, int $typeValue)
    {
        foreach (self::TYPE_MAPS as $key => $value) {
            if ($type != $key) {
                $query->where($value['foreign_key'], 0);
            } else {
                $query->where($value['foreign_key'], $typeValue);
            }
        }

        return $query;
    }

    /** @return  BelongsTo<Torrent, $this> */
    public function related_torrent()
    {
        return $this->belongsTo(Torrent::class, 'torrent');
    }

    /** @return  BelongsTo<User, $this> */
    public function create_user()
    {
        return $this->belongsTo(User::class, 'user')->withDefault(User::getDefaultUserAttributes());
    }

    /** @return  BelongsTo<User, $this> */
    public function update_user()
    {
        return $this->belongsTo(User::class, 'editedby')->withDefault(User::getDefaultUserAttributes());
    }
}
