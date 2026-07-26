<?php

namespace App\Models;

/**
 * @property int $torrent_count
 */
class ExamProgress extends NexusModel
{
    protected $fillable = ['exam_user_id', 'exam_id', 'uid', 'index', 'init_value', 'value', 'torrent_id'];

    public $timestamps = true;
}
