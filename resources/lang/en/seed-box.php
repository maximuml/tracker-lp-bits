<?php

use App\Models\SeedBoxRecord;

return [
    'type_text' => [
        SeedBoxRecord::TYPE_USER => 'User',
        SeedBoxRecord::TYPE_ADMIN => 'Administrator',
    ],
    'status_text' => [
        SeedBoxRecord::STATUS_UNAUDITED => 'Unaudited',
        SeedBoxRecord::STATUS_ALLOWED => 'Allowed',
        SeedBoxRecord::STATUS_DENIED => 'Denied',
    ],
    'status_change_message' => [
        'subject' => 'SeedBox record status changed',
        'body' => 'The status of your SeedBox record with ID :id was changed by :operator from :old_status to :new_status. Reason: :reason',
    ],
    'is_seed_box_yes' => 'This IP is SeedBox',
    'is_seed_box_no' => 'This IP is not SeedBox',
];
