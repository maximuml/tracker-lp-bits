<?php

use App\Enums\UsernameChangeType;

return [
    'change_type' => [
        UsernameChangeType::USER->value => 'User',
        UsernameChangeType::ADMIN->value => 'Administrator',
    ],
    'labels' => [
        'username_old' => 'Old username',
        'username_new' => 'New username',
        'change_type' => 'Change type',
    ],
];
