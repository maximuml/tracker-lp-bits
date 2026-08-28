<?php

use App\Enums\BusinessType;

return [
    'business_types' => [
        BusinessType::CANCEL_HIT_AND_RUN->value => 'Cancel H&R',
        BusinessType::BUY_MEDAL->value => 'Buy medal',
        BusinessType::BUY_ATTENDANCE_CARD->value => 'Buy attendance card',
        BusinessType::STICKY_PROMOTION->value => 'Sticky promotion',
        BusinessType::POST_REWARD->value => 'Post reward',
        BusinessType::EXCHANGE_UPLOAD->value => 'Exchange uploaded',
        BusinessType::EXCHANGE_INVITE->value => 'Buy invite',
        BusinessType::CUSTOM_TITLE->value => 'Custom title',
        BusinessType::BUY_VIP->value => 'Buy VIP',
        BusinessType::GIFT_TO_SOMEONE->value => 'Gift to someone',
        BusinessType::GIFT_TO_LOW_SHARE_RATIO->value => 'Gift to low share ratio',
        BusinessType::LUCKY_DRAW->value => 'Lucky draw',
        BusinessType::EXCHANGE_DOWNLOAD->value => 'Exchange downloaded',
        BusinessType::BUY_TEMPORARY_INVITE->value => 'Buy temporary invite',
        BusinessType::BUY_RAINBOW_ID->value => 'Buy rainbow ID',
        BusinessType::BUY_CHANGE_USERNAME_CARD->value => 'Buy change username card',
        BusinessType::GIFT_MEDAL->value => 'Gift medal',
        BusinessType::BUY_TORRENT->value => 'Buy torrent',
        BusinessType::TASK_PASS_REWARD->value => 'Task finished reward',
        BusinessType::TASK_NOT_PASS_DEDUCT->value => 'Task unfinished deduct',
        BusinessType::REWARD_TORRENT->value => 'Reward torrent',
        BusinessType::SELF_ENABLE->value => 'Self enable',

        BusinessType::ROLE_WORK_SALARY->value => 'Role work salary',
        BusinessType::TORRENT_BE_DOWNLOADED->value => 'Torrent be downloaded',
        BusinessType::RECEIVE_REWARD->value => 'Receive reward',
        BusinessType::RECEIVE_GIFT->value => 'Receive gift',
        BusinessType::UPLOAD_TORRENT->value => 'Upload torrent',
        BusinessType::TORRENT_BE_REWARD->value => 'Torrent receive reward',
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
