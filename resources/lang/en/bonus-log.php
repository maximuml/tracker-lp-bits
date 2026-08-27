<?php

use App\Models\BonusLogs;

return [
    'business_types' => [
        BonusLogs::BUSINESS_TYPE_CANCEL_HIT_AND_RUN => 'Cancel H&R',
        BonusLogs::BUSINESS_TYPE_BUY_MEDAL => 'Buy medal',
        BonusLogs::BUSINESS_TYPE_BUY_ATTENDANCE_CARD => 'Buy attendance card',
        BonusLogs::BUSINESS_TYPE_STICKY_PROMOTION => 'Sticky promotion',
        BonusLogs::BUSINESS_TYPE_POST_REWARD => 'Post reward',
        BonusLogs::BUSINESS_TYPE_EXCHANGE_UPLOAD => 'Exchange uploaded',
        BonusLogs::BUSINESS_TYPE_EXCHANGE_INVITE => 'Buy invite',
        BonusLogs::BUSINESS_TYPE_CUSTOM_TITLE => 'Custom title',
        BonusLogs::BUSINESS_TYPE_BUY_VIP => 'Buy VIP',
        BonusLogs::BUSINESS_TYPE_GIFT_TO_SOMEONE => 'Gift to someone',
        BonusLogs::BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO => 'Gift to low share ratio',
        BonusLogs::BUSINESS_TYPE_LUCKY_DRAW => 'Lucky draw',
        BonusLogs::BUSINESS_TYPE_EXCHANGE_DOWNLOAD => 'Exchange downloaded',
        BonusLogs::BUSINESS_TYPE_BUY_TEMPORARY_INVITE => 'Buy temporary invite',
        BonusLogs::BUSINESS_TYPE_BUY_RAINBOW_ID => 'Buy rainbow ID',
        BonusLogs::BUSINESS_TYPE_BUY_CHANGE_USERNAME_CARD => 'Buy change username card',
        BonusLogs::BUSINESS_TYPE_GIFT_MEDAL => 'Gift medal',
        BonusLogs::BUSINESS_TYPE_BUY_TORRENT => 'Buy torrent',
        BonusLogs::BUSINESS_TYPE_TASK_PASS_REWARD => 'Task finished reward',
        BonusLogs::BUSINESS_TYPE_TASK_NOT_PASS_DEDUCT => 'Task unfinished deduct',
        BonusLogs::BUSINESS_TYPE_REWARD_TORRENT => 'Reward torrent',
        BonusLogs::BUSINESS_TYPE_SELF_ENABLE => 'Self enable',

        BonusLogs::BUSINESS_TYPE_ROLE_WORK_SALARY => 'Role work salary',
        BonusLogs::BUSINESS_TYPE_TORRENT_BE_DOWNLOADED => 'Torrent be downloaded',
        BonusLogs::BUSINESS_TYPE_RECEIVE_REWARD => 'Receive reward',
        BonusLogs::BUSINESS_TYPE_RECEIVE_GIFT => 'Receive gift',
        BonusLogs::BUSINESS_TYPE_UPLOAD_TORRENT => 'Upload torrent',
        BonusLogs::BUSINESS_TYPE_TORRENT_BE_REWARD => 'Torrent receive reward',
    ],
    'fields' => [
        'business_type' => 'Business type',
        'old_total_value' => 'Pre-trade value',
        'value' => 'Trade value',
        'new_total_value' => 'Post-trade value',
    ],
    'exclude_seeding_bonus' => 'Exclude seeding bonus',
    'title_for_user' => 'User bonus details',
    'category' => 'Category',
    'category_common' => 'Common',
    'view_detail' => 'Details',
];
