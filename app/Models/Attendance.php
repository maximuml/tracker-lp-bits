<?php

/**
 * @property int $id
 * @property int $uid
 * @property string $added
 * @property int $points
 * @property int $days
 * @property int $total_days
 */
namespace App\Models;

/**
 * @property int $max_id
 */
class Attendance extends NexusModel
{
    /** @var  string */
    protected $table = 'attendance';

    /** @var  list<string> */
    protected $fillable = ['uid', 'added', 'points', 'days', 'total_days'];

    /** @var  array<string, string> */
    protected $casts = [
        'added' => 'datetime',
    ];

    const INITIAL_BONUS = 10;
    const STEP_BONUS = 5;
    const MAX_BONUS = 1000;
    const CONTINUOUS_BONUS = [
        10 => 200,
        20 => 500,
        30 => 1000
    ];

    const MAX_RETROACTIVE_DAYS = 30;


    /** @return  \Illuminate\Database\Eloquent\Relations\HasMany<AttendanceLog, $this> */
    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'uid', 'uid');
    }

}
