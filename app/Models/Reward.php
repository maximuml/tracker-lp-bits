<?php

namespace App\Models;


class Reward extends NexusModel
{
    protected $table = 'magic';

    protected $fillable = ['torrentid', 'userid', 'value', ];

    public $timestamps = true;

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
