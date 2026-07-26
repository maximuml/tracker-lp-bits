<?php

namespace App\Models;

/**
 * @property int $torrent_count
 */
class ExamProgress extends NexusModel
{
    /** @var  list<string> */
    protected $fillable = ['exam_user_id', 'exam_id', 'uid', 'index', 'init_value', 'value', 'torrent_id'];

    /** @var  bool */
    public $timestamps = true;
}
