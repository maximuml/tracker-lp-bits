<?php

/**
 * @property int $id
 * @property int $uid
 * @property string $ip
 * @property string|null $country
 * @property string|null $city
 * @property string|null $client
 * @property string|null $created_at
 * @property string|null $updated_at
 */
namespace App\Models;

class LoginLog extends NexusModel
{
    /** @var  bool */
    public $timestamps = true;

    /** @var  list<string> */
    protected $fillable = [
        'uid', 'ip', 'country', 'city', 'client'
    ];


}
