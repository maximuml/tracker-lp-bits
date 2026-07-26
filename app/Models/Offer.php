<?php

/**
 * @property int $id
 * @property int $userid
 * @property string $name
 * @property string|null $descr
 * @property string|null $added
 * @property string|null $allowedtime
 * @property int $yeah
 * @property int $against
 * @property int $category
 * @property int $comments
 * @property string $allowed
 */
namespace App\Models;


class Offer extends NexusModel
{
    protected $fillable = ['userid', 'name', 'descr', 'comments', 'added'];

    protected $casts = [
        'added' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }

}
