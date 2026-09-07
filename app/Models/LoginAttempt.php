<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoginAttemptType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $ip
 * @property string|null $added
 * @property bool $banned
 * @property int $attempts
 * @property LoginAttemptType $type
 */
class LoginAttempt extends Model
{
    protected $table = 'loginattempts';

    public $timestamps = false;

    /** @var array<string, string> */
    protected $casts = [
        'added' => 'datetime',
        'banned' => 'boolean',
        'attempts' => 'integer',
        'type' => LoginAttemptType::class,
    ];

    protected $fillable = ['ip', 'added', 'banned', 'attempts', 'type'];
}
