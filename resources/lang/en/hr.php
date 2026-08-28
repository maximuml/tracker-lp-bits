<?php

use App\Enums\HitAndRunMode;
use App\Enums\HitAndRunStatus;

return [
    'status_'.HitAndRunStatus::INSPECTING->value => 'Inspecting',
    'status_'.HitAndRunStatus::REACHED->value => 'Reached',
    'status_'.HitAndRunStatus::UNREACHED->value => 'Unreached',
    'status_'.HitAndRunStatus::PARDONED->value => 'Pardoned',

    'mode_'.HitAndRunMode::DISABLED->value => 'Disabled',
    'mode_'.HitAndRunMode::MANUAL->value => 'Manual',
    'mode_'.HitAndRunMode::GLOBAL->value => 'Global',

    'reached_by_seed_time_comment' => 'Up to：:now，seed time: :seed_time Hour(s) reached :seed_time_minimum Hour(s)',
    'reached_by_leech_time_comment' => 'Up to：:now，leech time: :leech_time Hour(s) reached :leech_time_minimum Hour(s)',
    'reached_by_share_ratio_comment' => "Up to：:now \nseed time: :seed_time Hour(s) Unreached :seed_time_minimum Hour(s) \nShare ratio: :share_ratio reached standard：:ignore_when_ratio_reach",
    'reached_by_special_user_class_comment' => 'Your user class: :user_class_text or donated user, ignore this H&R',
    'reached_message_subject' => 'H&R(ID: :hit_and_run_id) reached!',
    'reached_message_content' => 'Congratulation! The torrent: :torrent_name(ID: :torrent_id) you download at: :completed_at has reach the requirement.',

    'unreached_comment' => "Up to：:now \nseed time： :seed_time Hour(s) Unreached the requirement：:seed_time_minimum Hour(s) \nshare ratio：:share_ratio unreached the requirement：:ignore_when_ratio_reach too",
    'unreached_message_subject' => 'H&R(ID: :hit_and_run_id) unreached!',
    'unreached_message_content' => 'The torrent :torrent_name(ID: :torrent_id) you downloaded on :completed_at: did not reached! Please note that accumulating a certain number of H&R your account will be disabled.',

    'unreached_disable_comment' => 'H&R quantity reached the upper limit and account was disabled by the system',
    'unreached_disable_message_content' => 'Your account has been disabled because the cumulative H&R count has been reached the system limit: :ban_user_when_counts_reach',

    'bonus_cancel_comment' => 'spend :bonus canceled',
    'remove_confirm_msg' => 'Eliminate an H&R by deducting :bonus bonus, OK?',
];
