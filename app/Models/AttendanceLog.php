<?php

/**
 * @property int $id
 * @property int $uid
 * @property int $points
 * @property string $date
 * @property int $is_retroactive
 * @property string $created_at
 * @property string $updated_at
 */
namespace App\Models;

class AttendanceLog extends NexusModel
{
    protected $table = 'attendance_logs';

    protected $fillable = ['uid', 'points', 'date', 'is_retroactive'];

    public $timestamps = true;

}
