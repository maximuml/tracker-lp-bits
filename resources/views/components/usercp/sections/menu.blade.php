<x-tabs :tabs="[
    ['id' => 'home', 'url' => '/usercp', 'label' => __('usercp.text_user_cp_home')],
    ['id' => 'personal', 'url' => '?action=personal', 'label' => __('usercp.text_personal_settings')],
    ['id' => 'tracker', 'url' => '?action=tracker', 'label' => __('usercp.text_tracker_settings')],
    ['id' => 'forum', 'url' => '?action=forum', 'label' => __('usercp.text_forum_settings')],
    ['id' => 'security', 'url' => '?action=security', 'label' => __('usercp.text_security_settings')],
]" :active="$selected" :label="__('usercp.head_control_panel')" />
