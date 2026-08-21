<?php

use App\Models\UsernameChangeLog;

return [
    'change_type' => [
        UsernameChangeLog::CHANGE_TYPE_USER => 'User',
        UsernameChangeLog::CHANGE_TYPE_ADMIN => 'Administrator',
    ],
    'labels' => [
        'username_old' => 'Old username',
        'username_new' => 'New username',
        'change_type' => 'Change type',
    ],
];
