<?php

use App\Enums\ExamUserStatus;

return [
    'admin' => [
        'list' => [
            'page_title' => 'Exam users',
        ],
    ],
    'status' => [
        ExamUserStatus::FINISHED->value => 'Finished',
        ExamUserStatus::AVOIDED->value => 'Avoided',
        ExamUserStatus::NORMAL->value => 'Normal',
    ],
    'end_can_not_before_begin' => "End time: :end can't be before begin time: :begin",
    'status_not_allow_update_end' => 'Current status is not::status_text, unable to change end time',
];
