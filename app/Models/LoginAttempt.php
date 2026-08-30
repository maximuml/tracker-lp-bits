<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $ip
 * @property string|null $added
 * @property bool $banned
 * @property int $attempts
 * @property string $type
 */
class LoginAttempt extends Model
{
    protected $table = 'loginattempts';

    public $timestamps = false;

    /** @var array<string, string> */
    protected $casts = [
        'banned' => 'boolean',
    ];

    protected $fillable = ['ip', 'added', 'banned', 'attempts', 'type'];
}
