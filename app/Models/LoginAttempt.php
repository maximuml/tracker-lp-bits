<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $ip
 * @property string|null $added
 * @property string $banned
 * @property int $attempts
 * @property string $type
 */
class LoginAttempt extends Model
{
    protected $table = 'loginattempts';

    public $timestamps = false;

    protected $fillable = ['ip', 'added', 'banned', 'attempts', 'type'];
}
