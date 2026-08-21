<?php

use App\Models\ExamUser;

return [
    'admin' => [
        'list' => [
            'page_title' => 'Exam users',
        ],
    ],
    'status' => [
        ExamUser::STATUS_FINISHED => 'Finished',
        ExamUser::STATUS_AVOIDED => 'Avoided',
        ExamUser::STATUS_NORMAL => 'Normal',
    ],
    'end_can_not_before_begin' => "End time: :end can't be before begin time: :begin",
    'status_not_allow_update_end' => 'Current status is not::status_text, unable to change end time',
];
