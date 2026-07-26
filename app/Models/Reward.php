<?php

/**
 * @property int $id
 * @property int $torrentid
 * @property int $userid
 * @property int $value
 * @property string $created_at
 * @property string $updated_at
 */
namespace App\Models;


class Reward extends NexusModel
{
    /** @var  string */
    protected $table = 'magic';

    /** @var  list<string> */
    protected $fillable = ['torrentid', 'userid', 'value', ];

    /** @var  bool */
    public $timestamps = true;

    /** @return  \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
