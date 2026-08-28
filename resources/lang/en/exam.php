<?php

use App\Enums\ExamFilterUser;
use App\Enums\ExamIndex;

return [
    'label' => 'Exam',
    'name' => 'Exam name',
    'index' => 'Exam index',
    'time_range' => 'Exam time',
    'index_text_'.ExamIndex::UPLOADED->value => 'Upload increment',
    'index_text_'.ExamIndex::SEED_TIME_AVERAGE->value => 'Seed time average',
    'index_text_'.ExamIndex::DOWNLOADED->value => 'Download increment',
    'index_text_'.ExamIndex::SEED_BONUS->value => 'Bonus increment',
    'index_text_'.ExamIndex::SEED_POINTS->value => 'Seed points increment',
    'index_text_'.ExamIndex::UPLOAD_TORRENT_COUNT->value => 'Upload torrent increment',
    'filters' => [
        ExamFilterUser::USER_CLASS->value => 'User class',
        ExamFilterUser::REGISTER_TIME_RANGE->value => 'Register time range',
        ExamFilterUser::DONATE->value => 'Donated',
        ExamFilterUser::REGISTER_DAYS_RANGE->value => 'Range of days of registration',
    ],
    'require_value' => 'Require',
    'current_value' => 'Current',
    'result' => 'Result',

    'result_pass_for_exam' => 'Passed!',
    'result_pass_for_task' => 'Completed!',
    'result_not_pass_for_exam' => '<span style="color: red">Not Passed!</span>',
    'result_not_pass_for_task' => '<span style="color: red">Not Completed!</span>',
    'checkout_pass_message_subject_for_exam' => 'Exam passed!',
    'checkout_pass_message_content_for_exam' => 'Congratulation! You have pass the exam: :exam_name in time(:begin ~ :end)',
    'checkout_not_pass_message_subject_for_exam' => 'Exam not pass, and account is banned!',
    'checkout_not_pass_message_content_for_exam' => 'You did not pass the exam: :exam_name in time(:begin ~ :end), and your account has be banned!',

    'checkout_pass_message_subject_for_task' => 'Task completed!',
    'checkout_pass_message_content_for_task' => 'Congratulation! You have complete the task: :exam_name in time(:begin ~ :end), got bonus: :success_reward_bonus',
    'checkout_not_pass_message_subject_for_task' => 'Task not completed!',
    'checkout_not_pass_message_content_for_task' => 'You dit not complete the task: :exam_name in time (:begin ~ :end), deduct bonus: :fail_deduct_bonus.',

    'ban_log_reason' => 'Not complete exam: :exam_name in time(:begin ~ :end)',
    'ban_user_modcomment' => 'Due to not complete exam: :exam_name(:begin ~ :end), ban by system.',
    'admin' => [
        'list' => [
            'page_title' => 'Exam List',
        ],
    ],
    'recurring' => 'recurring',
    'recurring_daily' => 'once a day',
    'recurring_weekly' => 'once a week',
    'recurring_monthly' => 'once a month',
    'recurring_help' => 'If specified as periodic, the start time of the exam is the start time of the current cycle, and the end time is the end time of the current cycle, which are all natural days/weeks/months as stated here. If type is exam, at the end of each cycle, if the user still meets the screening criteria, the user will be automatically assigned an exam for the next cycle.',

    'time_condition_invalid' => 'The time parameter does not make sense, there are and only one of three items: start time + end time / duration / recurring',

    'type_exam' => 'Exam',
    'type_task' => 'Task',
    'type' => 'Type',
    'type_help' => 'Exam are regular exam and failing them will result in account banning. Tasks can be set to reward bonus or deduct bonus depending on whether they are completed or not',

    'fail_deduct_bonus' => 'Deduct bonus for failure',
    'success_reward_bonus' => 'Reward bonus for completion',

    'action_claim_task' => 'Claim',
    'confirm_to_claim' => 'Sure you want to claim?',
    'claim_by_yourself_only' => 'Claim only by yourself!',
    'not_match_target_user' => 'You are not a matching target user!',
    'has_other_on_the_way' => 'There is an other :type_text in progress!',
    'claimed_already' => 'Already claimed',
    'not_between_begin_end_time' => 'Not between begin & end time',
    'reach_max_user_count' => 'The number of claimed users has reached its maximum',
    'claimed_user_count' => 'Claimed',
    'max_user_count' => 'Max claim user count(0 means unlimited)',
    'background_color' => 'Info box background color',
];
