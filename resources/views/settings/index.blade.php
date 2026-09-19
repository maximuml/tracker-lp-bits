@extends('layouts.legacy')

@section('title', __('legacy/settings.head_website_settings'))

@section('content')
<h1 align="center"><a class="faqlink" href="{{ $scriptName }}">{{ __('legacy/settings.text_website_settings') ?? 'Website Settings' }}</a></h1>
<div class="nx-fgrid nx-fgrid--pad10">
<div class="nx-ffull nx-fbanner">
<span class="nx-color-white">{{ __('legacy/settings.text_configuration_file_saving_note') ?? 'Settings are stored in the database.' }}</span>
</td></tr>

@if ($action === 'showmenu')
    @foreach ([
        'basicsettings' => ['row_basic_settings', 'submit_basic_settings', 'text_basic_settings_note'],
        'mainsettings' => ['row_main_settings', 'submit_main_settings', 'text_main_settings_note'],
        'smtpsettings' => ['row_smtp_settings', 'submit_smtp_settings', 'text_smtp_settings_note'],
        'securitysettings' => ['row_security_settings', 'submit_security_settings', 'text_security_settings_note'],
        'authoritysettings' => ['row_authority_settings', 'submit_authority_settings', 'text_authority_settings_note'],
        'tweaksettings' => ['row_tweak_settings', 'submit_tweak_settings', 'text_tweak_settings_note'],
        'bonussettings' => ['row_bonus_settings', 'submit_bonus_settings', 'text_bonus_settings_note'],
        'accountsettings' => ['row_account_settings', 'submit_account_settings', 'text_account_settings_settings'],
        'torrentsettings' => ['row_torrents_settings', 'submit_torrents_settings', 'text_torrents_settings_note'],
        'attachmentsettings' => ['row_attachment_settings', 'submit_attachment_settings', 'text_attachment_settings_note'],
        'miscsettings' => ['row_misc_settings', 'submit_misc_settings', 'text_misc_settings_note'],
    ] as $act => [$row, $btn, $note])
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.'.$row) }}</div><div class="nx-fcell">
        <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="{{ $act }}">@csrf
        <input type="submit" value="{{ __('legacy/settings.'.$btn) }}"> {{ __('legacy/settings.'.$note) }}
        </form>
    </div>
    @endforeach

@elseif ($action === 'basicsettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_basic">@csrf
    <x-settings-row layout="grid" :label="__('legacy/settings.row_site_name')">
        <input type="text" name="SITENAME" value="{{ (string)($config['SITENAME'] ?? 'Nexus') }}"> {{ __('legacy/settings.text_site_name_note') ?? '' }}
    </x-settings-row>
    <x-settings-row layout="grid" :label="__('legacy/settings.row_base_url')">
        <input type="text" name="BASEURL" value="{{ (string)($config['BASEURL'] ?? '') }}"> . <b><u>{{ __('legacy/settings.text_base_url_note') }}</u> {{ __('legacy/settings.text_base_url_note_end') }}</b>
    </x-settings-row>
    <x-settings-save layout="grid" :label="__('legacy/settings.row_save_settings')" :text="__('legacy/settings.submit_save_settings')" />
    </form>

@elseif ($action === 'mainsettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_main">@csrf
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_site_online')" name="site_online" :value="$config['site_online'] ?? 'yes'" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_site_online_note'))" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_invite_system')" name="invitesystem" :value="$config['invitesystem'] ?? 'yes'" :note="__('legacy/settings.text_invite_system_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_initial_uploading_amount')" name="iniupload" :value="$config['iniupload'] ?? 0" :note="__('legacy/settings.text_initial_uploading_amount_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_initial_invites')" name="invite_count" :value="$config['invite_count'] ?? 0" :note="__('legacy/settings.text_initial_invites_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_initial_tmp_invites')" name="tmp_invite_count" :value="$config['tmp_invite_count'] ?? 0" :note="__('legacy/settings.text_initial_tmp_invites_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_invite_timeout')" name="invite_timeout" :value="$config['invite_timeout'] ?? 0" :note="__('legacy/settings.text_invite_timeout_note')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_complain_enabled')" name="complain_enabled" :value="$config['complain_enabled'] ?? 'no'" :note="__('legacy/settings.row_complain_enabled_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_registration_system')" name="registration" :value="$config['registration'] ?? 'yes'" :note="__('legacy/settings.row_allow_registrations')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-radios layout="grid"         :label="__('legacy/settings.row_verification_type')"
        name="verification"
        :options="['email' => __('legacy/settings.text_email'), 'admin' => __('legacy/settings.text_admin'), 'automatic' => __('legacy/settings.text_automatically')]"
        :selected="$config['verification'] ?? 'email'"
        :note="__('legacy/settings.text_verification_type_note')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_wait_system')" name="waitsystem" :value="$config['waitsystem'] ?? 'no'" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_wait_system_note'))" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_max_slots_system')" name="maxdlsystem" :value="$config['maxdlsystem'] ?? 'no'" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_max_slots_system_note'))" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_show_polls')" name="showpolls" :value="$config['showpolls'] ?? 'yes'" :note="__('legacy/settings.text_show_polls_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_show_stats')" name="showstats" :value="$config['showstats'] ?? 'yes'" :note="__('legacy/settings.text_show_stats_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_show_last_posts')" name="showlastxforumposts" :value="$config['showlastxforumposts'] ?? 'yes'" :note="__('legacy/settings.text_show_last_posts_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_show_last_torrents')" name="showlastxtorrents" :value="$config['showlastxtorrents'] ?? 'yes'" :note="__('legacy/settings.text_show_last_torrents_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_show_server_load')" name="showtrackerload" :value="$config['showtrackerload'] ?? 'yes'" :note="__('legacy/settings.text_show_server_load_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_show_forum_stats')" name="showforumstats" :value="$config['showforumstats'] ?? 'yes'" :note="__('legacy/settings.text_show_forum_stats_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_show_top_uploader')" name="show_top_uploader" :value="$config['show_top_uploader'] ?? 'yes'" :note="__('legacy/settings.text_show_top_uploader_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_nfo')" name="enablenfo" :value="$config['enablenfo'] ?? 'yes'" :note="__('legacy/settings.text_enable_nfo_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_technical_info')" name="enable_technical_info" :value="$config['enable_technical_info'] ?? 'yes'" :note="__('legacy/settings.text_enable_technical_info')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_global_search_system')" name="enable_global_search" :value="$config['enable_global_search'] ?? 'no'" :note="__('legacy/settings.text_global_search_system_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_show_shoutbox')" name="showshoutbox" :value="$config['showshoutbox'] ?? 'yes'" :note="__('legacy/settings.text_show_shoutbox_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_offer_section')" name="showoffer" :value="$config['showoffer'] ?? 'yes'" :note="__('legacy/settings.text_offer_section_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_show_donation')" name="donation" :value="$config['donation'] ?? 'no'" :note="__('legacy/settings.text_show_donation_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_weekend_free_uploading')" name="sptime" :value="$config['sptime'] ?? 'no'" :note="__('legacy/settings.text_weekend_free_uploading_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_bitbucket')" name="enablebitbucket" :value="$config['enablebitbucket'] ?? 'no'" :note="__('legacy/settings.text_bitbucket_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_ptshow_naming_style')" name="altname" :value="$config['altname'] ?? 'no'" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_ptshow_naming_style_note'))" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-radios layout="grid"         :label="__('legacy/settings.row_torrents_category_mode')"
        name="browsecat"
        :options="collect($searchboxes ?? [])->mapWithKeys(fn ($sb) => [(string)((array)$sb)['id'] => ((array)$sb)['name']])->all()"
        :selected="(string)($config['browsecat'] ?? 0)"
        :note="__('legacy/settings.text_torrents_category_mode_note')" />
    <x-settings-checkboxes layout="grid"         :label="__('legacy/settings.row_site_language_enabled')"
        name="site_language_enabled[]"
        :options="collect($allSiteLanguages ?? [])->map(fn ($l) => ['value' => $l->site_lang_folder, 'label' => $l->lang_name, 'checked' => in_array($l->site_lang_folder, $allEnabledLangs ?? [])])->all()"
        :note="__('legacy/settings.text_site_language_enabled_note')" />
    <x-settings-radios layout="grid"         :label="__('legacy/settings.row_default_site_language')"
        name="defaultlang"
        :options="collect($allSiteLanguages ?? [])->map(fn ($l) => ['value' => $l->site_lang_folder, 'label' => $l->lang_name, 'disabled' => ! in_array($l->site_lang_folder, $allEnabledLangs ?? [])])->all()"
        :selected="(string)($config['defaultlang'] ?? '')"
        :note="__('legacy/settings.text_default_site_language_note')" />
    <x-settings-select layout="grid"         :label="__('legacy/settings.row_default_stylesheet')"
        name="defstylesheet"
        :options="collect($stylesheets ?? [])->mapWithKeys(fn ($ss) => [(string)((array)$ss)['id'] => ((array)$ss)['name']])->all()"
        :selected="(string)($config['defstylesheet'] ?? 0)"
        :note="__('legacy/settings.text_default_stylesheet_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_site_logo')" name="logo" :value="$config['logo'] ?? ''" :note="__('legacy/settings.text_site_logo_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_max_torrent_size')" name="max_torrent_size" :value="$config['max_torrent_size'] ?? 1048576" :note="__('legacy/settings.text_max_torrent_size_note')" />
    <x-settings-row layout="grid" :label="__('legacy/settings.row_announce_interval')">
        {{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_announce_interval_note_one')) ?? '' }}<br>
        <ul>
            <li>{{ __('legacy/settings.text_announce_default') ?? '' }}<input type="text" name="announce_interval" value="{{ (string)($config['announce_interval'] ?? 1800) }}"> {{ __('legacy/settings.text_announce_default_default') ?? '' }}</li>
            <li>{{ __('legacy/settings.text_for_torrents_older_than') ?? '' }}<input type="text" name="annintertwoage" value="{{ (string)($config['annintertwoage'] ?? 7) }}">{{ __('legacy/settings.text_days') ?? 'days' }}<input type="text" name="annintertwo" value="{{ (string)($config['annintertwo'] ?? 2700) }}"> {{ __('legacy/settings.text_announce_two_default') ?? '' }}</li>
            <li>{{ __('legacy/settings.text_for_torrents_older_than') ?? '' }}<input type="text" name="anninterthreeage" value="{{ (string)($config['anninterthreeage'] ?? 30) }}">{{ __('legacy/settings.text_days') ?? 'days' }}<input type="text" name="anninterthree" value="{{ (string)($config['anninterthree'] ?? 3600) }}"> {{ __('legacy/settings.text_announce_three_default') ?? '' }}</li>
        </ul>
        {{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_announce_interval_note_two')) ?? '' }}
    </x-settings-row>
    <x-settings-row layout="grid" :label="__('legacy/settings.row_cleanup_interval')">
        {{ __('legacy/settings.text_cleanup_interval_note_one') ?? '' }}<br>
        <ul>
            @foreach (['one' => 'autoclean_interval_one', 'two' => 'autoclean_interval_two', 'three' => 'autoclean_interval_three', 'four' => 'autoclean_interval_four', 'five' => 'autoclean_interval_five'] as $pri => $field)
                <li>{{ __('legacy/settings.'."text_priority_{$pri}") }}<input type="text" name="{{ $field }}" value="{{ (string)($config[$field] ?? '') }}"> {{ __('legacy/settings.'."text_priority_{$pri}_note") }}</li>
            @endforeach
        </ul>
        <b>{{ __('legacy/settings.text_cleanup_interval_note_two') }}</b>: {{ __('legacy/settings.text_cleanup_interval_note_do') }} <b>{{ __('legacy/settings.text_cleanup_interval_note_not') }}</b> {{ __('legacy/settings.text_cleanup_interval_note_two_end') }}
    </x-settings-row>
    <x-settings-text layout="grid" :label="__('legacy/settings.row_signup_timeout')" name="signup_timeout" :value="$config['signup_timeout'] ?? 259200" :note="__('legacy/settings.text_signup_timeout_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_min_offer_votes')" name="minoffervotes" :value="$config['minoffervotes'] ?? 15" :note="__('legacy/settings.text_min_offer_votes_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_offer_vote_timeout')" name="offervotetimeout" :value="$config['offervotetimeout'] ?? 259200" :note="__('legacy/settings.text_offer_vote_timeout_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_offer_upload_timeout')" name="offeruptimeout" :value="$config['offeruptimeout'] ?? 86400" :note="__('legacy/settings.text_offer_upload_timeout_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_offer_skip_approved_count')" name="offer_skip_approved_count" :value="$config['offer_skip_approved_count'] ?? ''" :note="__('legacy/settings.text_offer_skip_approved_count_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_upload_deny_approval_deny_count')" name="upload_deny_approval_deny_count" :value="$config['upload_deny_approval_deny_count'] ?? ''" :note="__('legacy/settings.text_upload_deny_approval_deny_count_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_max_subtitle_size')" name="maxsubsize" :value="$config['maxsubsize'] ?? 3145728" :note="__('legacy/settings.text_max_subtitle_size_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_posts_per_page')" name="postsperpage" :value="$config['postsperpage'] ?? 10" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_posts_per_page_note'))" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_topics_per_page')" name="topicsperpage" :value="$config['topicsperpage'] ?? 20" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_topics_per_page_note'))" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_torrents_per_page')" name="torrentsperpage" :value="$config['torrentsperpage'] ?? 50" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_torrents_per_page_note'))" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_number_of_news')" name="maxnewsnum" :value="$config['maxnewsnum'] ?? 3" :note="__('legacy/settings.text_number_of_news_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_torrent_dead_time')" name="max_dead_torrent_time" :value="$config['max_dead_torrent_time'] ?? 21600" :note="__('legacy/settings.text_torrent_dead_time_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_max_users')" name="maxusers" :value="$config['maxusers'] ?? 2500" :note="__('legacy/settings.text_max_users')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_site_accountant_userid')" name="ACCOUNTANTID" :value="$config['ACCOUNTANTID'] ?? ''" :note="__('legacy/settings.text_site_accountant_userid_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_alipay_account')" name="ALIPAYACCOUNT" :value="$config['ALIPAYACCOUNT'] ?? ''" :note="__('legacy/settings.text_alipal_account_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_paypal_account')" name="PAYPALACCOUNT" :value="$config['PAYPALACCOUNT'] ?? ''" :note="__('legacy/settings.text_paypal_account_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_site_email')" name="SITEEMAIL" :value="$config['SITEEMAIL'] ?? ''" :note="__('legacy/settings.text_site_email_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_report_email')" name="reportemail" :value="$config['reportemail'] ?? ''" :note="__('legacy/settings.text_report_email_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_site_slogan')" name="SLOGAN" :value="$config['SLOGAN'] ?? ''" :note="__('legacy/settings.text_site_slogan_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_icp_license')" name="icplicense" :value="$config['icplicense'] ?? ''" :note="__('legacy/settings.text_icp_license_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_torrent_directory')" name="torrent_dir" :value="$config['torrent_dir'] ?? 'torrents'" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_torrent_directory'))" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_bitbucket_directory')" name="bitbucket" :value="$config['bitbucket'] ?? 'bitbucket'" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_bitbucket_directory_note'))" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_torrent_name_prefix')" name="torrentnameprefix" :value="$config['torrentnameprefix'] ?? '[Nexus]'" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_torrent_name_prefix_note'))" />
    <x-settings-save layout="grid" :label="__('legacy/settings.row_save_settings')" :text="__('legacy/settings.submit_save_settings')" />
    </form>

@elseif ($action === 'smtpsettings')
    <form method="post" action="{{ $scriptName }}" name="smtpsettings_form"><input type="hidden" name="action" value="savesettings_smtp">@csrf
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_email_notification')" name="emailnotify" :value="$config['emailnotify'] ?? 'no'" :note="__('legacy/settings.text_email_notification_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-radios layout="grid"         :label="__('legacy/settings.row_mail_function_type')"
        name="smtptype"
        :options="['default' => __('legacy/settings.text_smtp_default'), 'advanced' => __('legacy/settings.text_smtp_advanced'), 'external' => __('legacy/settings.text_smtp_external'), 'none' => __('legacy/settings.text_smtp_none')]"
        :selected="$config['smtptype'] ?? 'default'"
        :break="true" />
    <tbody id="smtp_advanced"@if(($config['smtptype'] ?? 'default') !== 'advanced') class="nx-hidden"@endif>
    <div class="nx-ffull nx-center"><b>{{ __('legacy/settings.text_setting_for_advanced_type') ?? 'Advanced' }}</b></div>
    <x-settings-text layout="grid" :label="__('legacy/settings.row_smtp_host')" name="smtp_host" :value="$config['smtp_host'] ?? 'localhost'" :note="__('legacy/settings.text_smtp_host_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_smtp_port')" name="smtp_port" :value="$config['smtp_port'] ?? 25" :note="__('legacy/settings.text_smtp_port_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_smtp_sendmail_from')" name="smtp_from" :value="$config['smtp_from'] ?? ''" :note="__('legacy/settings.text_smtp_sendmail_from_note')" />
    </tbody>
    <tbody id="smtp_external"@if(($config['smtptype'] ?? 'default') !== 'external') class="nx-hidden"@endif>
    <div class="nx-ffull nx-center"><b>{{ __('legacy/settings.text_setting_for_external_type') ?? 'External' }}</b></div>
    <x-settings-text layout="grid" :label="__('legacy/settings.row_outgoing_mail_address')" name="smtpaddress" :value="$config['smtpaddress'] ?? ''" :note="__('legacy/settings.text_outgoing_mail_address_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_outgoing_mail_port')" name="smtpport" :value="$config['smtpport'] ?? ''" :note="__('legacy/settings.text_outgoing_mail_port_note')" />
    <x-settings-radios layout="grid"         :label="__('legacy/settings.row_outgoing_mail_encryption')"
        name="encryption"
        :options="['' => 'none', 'tls' => 'tls', 'ssl' => 'ssl']"
        :selected="(string)($config['encryption'] ?? '')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_smtp_account_name')" name="accountname" :value="$config['accountname'] ?? ''" :note="__('legacy/settings.text_smtp_account_name_note')" />
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.row_smtp_account_password') ?? 'Password' }}</div><div class="nx-fcell"><input type=password name=accountpassword value="{{ (string)($config['accountpassword'] ?? '') }}"> <b>{{ __('legacy/settings.text_smtp_account_password_note') }}</b> {{ __('legacy/settings.text_smtp_account_password_note_end') }}</div>
    </tbody>
    <x-settings-save layout="grid" :label="__('legacy/settings.row_save_settings')" :text="__('legacy/settings.submit_save_settings')" />
    </form>

@elseif ($action === 'securitysettings')
    <form method="post" action="{{ $scriptName }}" name="securitysettings_form"><input type="hidden" name="action" value="savesettings_security">@csrf
    <x-settings-radios layout="grid"         :label="__('legacy/settings.row_enable_ssl')"
        name="securelogin"
        :options="['yes' => __('legacy/settings.text_yes'), 'no' => __('legacy/settings.text_no'), 'op' => __('legacy/settings.text_optional')]"
        :selected="$config['securelogin'] ?? 'no'"
        :note="__('legacy/settings.text_ssl_note')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_image_verification')" name="iv" :value="$config['iv'] ?? 'yes'" :note="__('legacy/settings.text_image_verification_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_allow_email_change')" name="changeemail" :value="$config['changeemail'] ?? 'yes'" :note="__('legacy/settings.text_email_change_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-row layout="grid" :label="__('legacy/settings.row_cheater_detection_level')">
        <select name="cheaterdet">
            @foreach (['0' => 'select_none', '1' => 'select_conservative', '2' => 'select_normal', '3' => 'select_strict', '4' => 'select_paranoid'] as $val => $labelKey)
                <option value="{{ $val }}"@if ((string)($config['cheaterdet'] ?? 0) === $val) selected @endif> {{ __('legacy/settings.'.$labelKey) }} </option>
            @endforeach
        </select> {{ __('legacy/settings.text_cheater_detection_level_note') ?? '' }}<br>
        {{ __('legacy/settings.text_never_suspect') ?? '' }}{{ \App\Support\UserClass::classSelectWithContext('nodetect', (int)($authority['staffmem'] ?? 0), $config['nodetect'] ?? 0) }}{{ __('legacy/settings.text_or_above') ?? '' }}
    </x-settings-row>
    <x-settings-text layout="grid" :label="__('legacy/settings.row_max_ips')" name="maxip" :value="$config['maxip'] ?? 1" :note="__('legacy/settings.text_max_ips_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_max_login_attemps')" name="maxloginattempts" :value="$config['maxloginattempts'] ?? 7" :note="__('legacy/settings.text_max_login_attemps_note')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_use_challenge_response_authentication')" name="use_challenge_response_authentication" :value="$config['use_challenge_response_authentication'] ?? 'no'" :note="__('legacy/settings.text_use_challenge_response_authentication_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-radios layout="grid"         :label="__('legacy/settings.row_guest_visit_type')"
        name="guest_visit_type"
        :options="['normal' => __('legacy/settings.text_guest_visit_type_normal'), 'static_page' => __('legacy/settings.text_guest_visit_type_static_page'), 'custom_content' => __('legacy/settings.text_guest_visit_type_custom_content'), 'redirect' => __('legacy/settings.text_guest_visit_type_redirect')]"
        :selected="$config['guest_visit_type'] ?? 'normal'"
        :break="true" />
    <tbody id="tbody_static_page"@if(($config['guest_visit_type'] ?? '') !== 'static_page') class="nx-hidden"@endif>
    <x-settings-select layout="grid"         :label="__('legacy/settings.row_guest_visit_value_static_page')"
        name="guest_visit_value_static_page"
        :options="collect($staticPages ?? [])->mapWithKeys(fn ($p) => [$p => $p])->all()"
        :selected="(string)($config['guest_visit_value_static_page'] ?? '')"
        :note="__('legacy/settings.text_guest_visit_value_static_page')" />
    </tbody>
    <tbody id="tbody_custom_content"@if(($config['guest_visit_type'] ?? '') !== 'custom_content') class="nx-hidden"@endif>
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.row_guest_visit_value_custom_content') ?? 'Custom content' }}</div><div class="nx-fcell"><x-bbcode-editor form="securitysettings_form" text="guest_visit_value_custom_content" :content="$config['guest_visit_value_custom_content'] ?? ''" /></div>
    </tbody>
    <tbody id="tbody_redirect"@if(($config['guest_visit_type'] ?? '') !== 'redirect') class="nx-hidden"@endif>
    <x-settings-text layout="grid" :label="__('legacy/settings.row_guest_visit_value_redirect')" name="guest_visit_value_redirect" :value="$config['guest_visit_value_redirect'] ?? ''" />
    </tbody>
    <x-settings-row layout="grid" :label="__('legacy/settings.row_login_type')">
        @foreach (['normal' => 'text_login_type_normal', 'secret' => 'text_login_type_secret', 'passkey' => 'text_login_type_passkey'] as $val => $labelKey)
            <label><input type="radio" name="login_type" value="{{ $val }}"@if (($config['login_type'] ?? 'normal') === $val) checked @endif>{{ __('legacy/settings.'.$labelKey) }}</label>
        @endforeach
        <b>{{ __('legacy/settings.text_login_type_warning') ?? '' }}</b>
    </x-settings-row>
    <tbody id="tbody_login_secret"@if(!in_array($config['login_type'] ?? '', ['secret', 'passkey'])) class="nx-hidden"@endif>
    <x-settings-row layout="grid" :label="__('legacy/settings.row_login_secret')">
        {{ __('legacy/settings.text_login_secret_current') ?? 'Current secret' }}：{{ $config['login_secret'] ?? '' }}
        @if (! empty($config['login_secret']))
            <br>{{ __('legacy/settings.text_login_url_with_secret') ?? '' }}: {{ \App\Support\Url::schemeAndHost(false) }}/login.php?secret={{ $config['login_secret'] }}
        @endif
        <br><label><input type="radio" name="login_secret_regenerate" value="no"@if (! empty($config['login_secret'])) checked @endif>{{ __('legacy/settings.text_login_secret_regenerate_no') ?? 'No' }}</label>
        <br><label><input type="radio" name="login_secret_regenerate" value="yes"@if (empty($config['login_secret'])) checked @endif>{{ __('legacy/settings.text_login_secret_regenerate_yes') ?? 'Yes' }}</label>
    </x-settings-row>
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.row_login_secret_lifetime') ?? 'Secret lifetime' }}</div><div class="nx-fcell"><input type="text" name="login_secret_lifetime" value="{{ (string)($config['login_secret_lifetime'] ?? '') }}">{{ __('legacy/settings.text_login_secret_lifetime_unit') ?? ' min' }}</div>
    </tbody>
    <x-settings-save layout="grid" :label="__('legacy/settings.row_save_settings')" :text="__('legacy/settings.submit_save_settings')" />
    </form>

@elseif ($action === 'authoritysettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_authority">@csrf
    @foreach ([
        ['defaultclass', 'row_default_class', \App\Enums\UserClass::USER, 'text_default_class_note'],
        ['staffmem', 'row_staff_member', \App\Enums\UserClass::MODERATOR, 'text_staff_member_note'],
        ['newsmanage', 'row_news_management', \App\Enums\UserClass::ADMINISTRATOR, 'text_news_management_note'],
        ['sbmanage', 'row_shoutbox_management', \App\Enums\UserClass::MODERATOR, 'text_shoutbox_management_note'],
        ['pollmanage', 'row_poll_management', \App\Enums\UserClass::ADMINISTRATOR, 'text_poll_management_note'],
        ['postmanage', 'row_forum_post_management', \App\Enums\UserClass::MODERATOR, 'text_forum_post_management_note'],
        ['commanage', 'row_comment_management', \App\Enums\UserClass::MODERATOR, 'text_comment_management_note'],
        ['forummanage', 'row_forum_management', \App\Enums\UserClass::ADMINISTRATOR, 'text_forum_management_note'],
        ['viewuserlist', 'row_view_userlist', \App\Enums\UserClass::POWER_USER, 'text_view_userlist_note'],
        ['torrentmanage', 'row_torrent_management', \App\Enums\UserClass::MODERATOR, 'text_torrent_management_note'],
        ['torrent-delete', 'row_torrent_delete', \App\Enums\UserClass::ADMINISTRATOR, 'text_torrent_delete_note'],
        ['torrentsticky', 'row_torrent_sticky', \App\Enums\UserClass::ADMINISTRATOR, 'text_torrent_sticky_note'],
        ['torrenton_promotion', 'row_torrent_on_promotion', \App\Enums\UserClass::ADMINISTRATOR, 'text_torrent_promotion_note'],
        ['torrent_hr', 'row_torrent_hr', \App\Enums\UserClass::ADMINISTRATOR, 'text_torrent_hr_note'],
        ['torrent-set-special-tag', null, \App\Enums\UserClass::ADMINISTRATOR, null],
        ['torrent-approval', null, \App\Enums\UserClass::ADMINISTRATOR, null],
        ['torrent-approval-allow-automatic', null, \App\Enums\UserClass::UPLOADER, null],
        ['torrent-set-price', null, \App\Enums\UserClass::UPLOADER, null],
        ['askreseed', 'row_ask_for_reseed', \App\Enums\UserClass::POWER_USER, 'text_ask_for_reseed_note'],
        ['viewnfo', 'row_view_nfo', \App\Enums\UserClass::POWER_USER, 'text_view_nfo_note'],
        ['torrentstructure', 'row_view_torrent_structure', \App\Enums\UserClass::ULTIMATE_USER, 'text_view_torrent_structure_note'],
        ['sendinvite', 'row_send_invite', \App\Enums\UserClass::POWER_USER, 'text_send_invite_note'],
        ['viewhistory', 'row_view_history', \App\Enums\UserClass::VETERAN_USER, 'text_view_history_note'],
        ['topten', 'row_view_topten', \App\Enums\UserClass::POWER_USER, 'text_view_topten_note'],
        ['log', 'row_view_general_log', \App\Enums\UserClass::INSANE_USER, 'text_view_general_log_note'],
        ['confilog', 'row_view_confidential_log', \App\Enums\UserClass::MODERATOR, 'text_view_confidential_log_note'],
        ['userprofile', 'row_view_user_confidential', \App\Enums\UserClass::ADMINISTRATOR, 'text_view_user_confidential_note'],
        ['torrenthistory', 'row_view_user_torrent', \App\Enums\UserClass::POWER_USER, 'text_view_user_torrent_note'],
        ['prfmanage', 'row_general_profile_management', \App\Enums\UserClass::MODERATOR, 'text_general_profile_management_note'],
        ['cruprfmanage', 'row_crucial_profile_management', \App\Enums\UserClass::ADMINISTRATOR, 'text_crucial_profile_management_note'],
        ['uploadsub', 'row_upload_subtitle', \App\Enums\UserClass::USER, 'text_upload_subtitle_note'],
        ['delownsub', 'row_delete_own_subtitle', \App\Enums\UserClass::POWER_USER, 'text_delete_own_subtitle_note'],
        ['submanage', 'row_subtitle_management', \App\Enums\UserClass::MODERATOR, 'text_subtitle_management'],
        ['updateextinfo', 'row_update_external_info', \App\Enums\UserClass::EXTREME_USER, 'text_update_external_info_note'],
        ['viewanonymous', 'row_view_anonymous', \App\Enums\UserClass::UPLOADER, 'text_view_anonymous_note'],
        ['beanonymous', 'row_be_anonymous', \App\Enums\UserClass::CRAZY_USER, 'text_be_anonymous_note'],
        ['addoffer', 'row_add_offer', \App\Enums\UserClass::PEASANT, 'text_add_offer_note'],
        ['offermanage', 'row_offer_management', \App\Enums\UserClass::MODERATOR, 'text_offer_management_note'],
        ['upload', 'row_upload_torrent', \App\Enums\UserClass::POWER_USER, 'text_upload_torrent_note'],
        ['movetorrent', 'row_move_torrent', \App\Enums\UserClass::MODERATOR, 'text_move_torrent_note'],
        ['chrmanage', 'row_chronicle_management', \App\Enums\UserClass::MODERATOR, 'text_chronicle_management_note'],
        ['viewinvite', 'row_view_invite', \App\Enums\UserClass::MODERATOR, 'text_view_invite_note'],
        ['buyinvite', 'row_buy_invites', \App\Enums\UserClass::INSANE_USER, 'text_buy_invites_note'],
        ['seebanned', 'row_see_banned_torrents', \App\Enums\UserClass::UPLOADER, 'text_see_banned_torrents_note'],
        ['againstoffer', 'row_vote_against_offers', \App\Enums\UserClass::USER, 'text_vote_against_offers_note'],
        ['userbar', 'row_allow_userbar', \App\Enums\UserClass::POWER_USER, 'text_allow_userbar_note'],
        ['user-delete', null, \App\Enums\UserClass::ADMINISTRATOR, null],
        ['user-change-class', null, \App\Enums\UserClass::ADMINISTRATOR, null],
    ] as [$perm, $rowKey, $defaultClass, $noteKey])
    <x-settings-row layout="grid" :label="(string) ($rowKey !== null ? (__('legacy/settings.'.$rowKey)) : \App\Support\Locale::trans('permission.'.$perm.'.text', [], null))">
        {{ __('legacy/settings.text_minimum_class') ?? 'Min class: ' }}{{ \App\Support\UserClass::classSelectWithContext($perm, \App\Enums\UserClass::SYSOP->value, $config[$perm] ?? 0, 0, true) }}{{ __('legacy/settings.text_default') ?? ' Default: ' }}{{ \App\Support\UserClass::name($defaultClass->value, false, true, true) }}{{ $noteKey !== null ? (__('legacy/settings.'.$noteKey)) : \App\Support\Locale::trans('permission.'.$perm.'.desc', [], null) }}
    </x-settings-row>
    @endforeach
    <x-settings-save layout="grid" :label="__('legacy/settings.row_save_settings')" :text="__('legacy/settings.submit_save_settings')" />
    </form>

@elseif ($action === 'tweaksettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_tweak">@csrf
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_save_user_location')" name="where" :value="$config['where'] ?? 'no'" :note="__('legacy/settings.text_save_user_location_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-row layout="grid" :label="__('legacy/settings.row_kps_enabled')">
        @foreach (['enable' => 'text_enabled', 'disablesave' => 'text_disabled_but_save', 'disable' => 'text_disabled_no_save'] as $val => $labelKey)
            <input type="radio" id="bonus{{ $val }}" name="bonus" value="{{ $val }}"@if (($config['bonus'] ?? 'enable') === $val) checked @endif> <label for="bonus{{ $val }}">{{ __('legacy/settings.'.$labelKey) }}</label>
        @endforeach
        <br>{{ __('legacy/settings.text_kps_note') ?? '' }}
    </x-settings-row>
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_location')" name="enablelocation" :value="$config['enablelocation'] ?? 'no'" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_enable_location_note'))" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_tooltip')" name="enabletooltip" :value="$config['enabletooltip'] ?? 'no'" :note="__('legacy/settings.text_enable_tooltip_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_title_keywords')" name="titlekeywords" :value="$config['titlekeywords'] ?? ''" :note="__('legacy/settings.text_title_keywords_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_meta_keywords')" name="metakeywords" :value="$config['metakeywords'] ?? ''" :note="__('legacy/settings.text_meta_keywords_note')" />
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.row_meta_description') ?? 'Meta description' }}</div><div class="nx-fcell"><textarea cols="100" rows="5" name='metadescription'>{{ (string)($config['metadescription'] ?? '') }}</textarea><br>{{ __('legacy/settings.text_meta_description_note') ?? '' }}</div>
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.row_web_analytics_code') ?? 'Analytics code' }}</div><div class="nx-fcell"><textarea cols="100" rows="5" name='analyticscode'>{{ (string)($config['analyticscode'] ?? '') }}</textarea><br>{{ __('legacy/settings.text_web_analytics_code_note') }} <br /><b>{{ __('legacy/settings.text_note') }}</b>: {{ __('legacy/settings.text_web_analytics_code_note_end') }}</div>
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.row_see_sql_debug') ?? 'SQL debug' }}</div><div class="nx-fcell"><input type='checkbox' name='enablesqldebug' value='yes'@if (($config['enablesqldebug'] ?? 'no') === 'yes') checked @endif>{{ __('legacy/settings.text_allow') ?? 'Allow' }}{{ \App\Support\UserClass::classSelectWithContext('sqldebug', \App\Enums\UserClass::STAFFLEADER->value, $config['sqldebug'] ?? \App\Enums\UserClass::MODERATOR->value) }}{{ __('legacy/settings.text_see_sql_list') ?? '' }}{{ \App\Support\UserClass::name(\App\Enums\UserClass::SYSOP->value, false, true, true) }}</div>
    <x-settings-text layout="grid" :label="__('legacy/settings.row_tracker_founded_date')" name="datefounded" :value="$config['datefounded'] ?? '2007-12-24'" :note="__('legacy/settings.text_tracker_founded_date_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_css_date')" name="cssdate" :value="$config['cssdate'] ?? ''" :note="__('legacy/settings.text_css_date')" />
    <x-settings-save layout="grid" :label="__('legacy/settings.row_save_settings')" :text="__('legacy/settings.submit_save_settings')" />
    </form>

@elseif ($action === 'bonussettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_bonus">@csrf
    <div class="nx-ffull nx-center"><b>{{ __('legacy/settings.text_bonus_by_seeding') ?? 'Bonus by seeding' }}</b></div>
    <x-settings-text layout="grid" :label="__('legacy/settings.row_min_size')" name="min_size" :value="$config['min_size'] ?? 0" :note="__('legacy/settings.text_bonus_mini_size').' '.(__('legacy/settings.text_bonus_mini_size_help'))" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_donor_gets_double')" name="donortimes" :value="$config['donortimes'] ?? 2" :note="__('legacy/settings.text_donor_gets').' '.(__('legacy/settings.text_times_as_many'))" />
    <x-settings-row layout="grid" :label="__('legacy/settings.row_basic_seeding_bonus')">
        {{ __('legacy/settings.text_user_would_get') ?? '' }}<input type="text" name="perseeding" value="{{ (string)($config['perseeding'] ?? 1) }}">{{ __('legacy/settings.text_bonus_points') ?? '' }}<input type="text" name="maxseeding" value="{{ (string)($config['maxseeding'] ?? 7) }}">{{ __('legacy/settings.text_torrents_default') ?? '' }}
    </x-settings-row>
    <div class="nx-ffull nx-center"><b>{{ __('legacy/settings.text_misc_ways_get_bonus') ?? 'Misc bonus' }}</b></div>
    @foreach ([
        ['uploadtorrent', 'row_uploading_torrent', 15, 'text_uploading_torrent_note'],
        ['starttopic', 'row_starting_topic', 2, 'text_starting_topic_note'],
        ['makepost', 'row_making_post', 1, 'text_making_post_note'],
        ['addcomment', 'row_adding_comment', 1, 'text_adding_comment_note'],
        ['pollvote', 'row_voting_on_poll', 1, 'text_voting_on_poll_note'],
        ['offervote', 'row_voting_on_offer', 1, 'text_voting_on_offer_note'],
    ] as [$field, $rowKey, $default, $noteKey])
    <x-settings-row layout="grid" :label="__('legacy/settings.'.$rowKey)">
        {{ __('legacy/settings.text_user_would_get') ?? '' }}<input type="text" name="{{ $field }}" value="{{ (string)($config[$field] ?? $default) }}">{{ __('legacy/settings.'.$noteKey) }}
    </x-settings-row>
    @endforeach
    <x-settings-row layout="grid" :label="__('legacy/settings.row_saying_thanks')">
        {{ __('legacy/settings.text_giver_and_receiver_get') ?? '' }}<input type="text" name="saythanks" value="{{ (string)($config['saythanks'] ?? 0.5) }}">{{ __('legacy/settings.text_saying_thanks_and') ?? '' }}<input type="text" name="receivethanks" value="{{ (string)($config['receivethanks'] ?? 0) }}">{{ __('legacy/settings.text_saying_thanks_default') ?? '' }}
    </x-settings-row>
    <div class="nx-ffull nx-center"><b>{{ __('legacy/settings.text_things_cost_bonus') ?? 'Things that cost bonus' }}</b></div>
    @foreach ([
        ['onegbupload', 'row_one_gb_credit', 300, 'text_one_gb_credit_note'],
        ['fivegbupload', 'row_five_gb_credit', 800, 'text_five_gb_credit_note'],
        ['tengbupload', 'row_ten_gb_credit', 1200, 'text_ten_gb_credit_note'],
        ['hundredgbupload', 'row_hundred_gb_credit', 10000, 'text_hundred_gb_credit_note'],
        ['tengbdownload', 'row_ten_gb_download_credit', 1000, 'text_ten_gb_download_credit_note'],
        ['hundredgbdownload', 'row_hundred_gb_download_credit', 8000, 'text_hundred_gb_download_credit_note'],
        ['oneinvite', 'row_buy_an_invite', 1000, 'text_buy_an_invite_note'],
        ['one_tmp_invite', 'row_buy_an_tmp_invite', \App\Models\BonusLogs::DEFAULT_BONUS_BUY_TEMPORARY_INVITE, 'text_buy_an_tmp_invite_note'],
        ['customtitle', 'row_custom_title', 5000, 'text_custom_title_note'],
        ['vipstatus', 'row_vip_status', 8000, 'text_vip_status_note'],
        ['cancel_hr', 'row_cancel_hr', \App\Models\BonusLogs::DEFAULT_BONUS_CANCEL_ONE_HIT_AND_RUN, 'text_cancel_hr_note'],
        ['attendance_card', 'row_attendance_card', \App\Models\BonusLogs::DEFAULT_BONUS_BUY_ATTENDANCE_CARD, 'text_attendance_card_note'],
        ['rainbow_id', 'row_buy_rainbow_id', \App\Models\BonusLogs::DEFAULT_BONUS_BUY_RAINBOW_ID, 'text_buy_rainbow_id_note'],
        ['change_username_card', 'row_buy_change_username_card', \App\Models\BonusLogs::DEFAULT_BONUS_BUY_CHANGE_USERNAME_CARD, 'text_buy_change_username_card_note'],
        ['self_enable', 'row_self_enable', \App\Models\BonusLogs::DEFAULT_BONUS_SELF_ENABLE, 'text_self_enable_note'],
    ] as [$field, $rowKey, $default, $noteKey])
    <x-settings-row layout="grid" :label="__('legacy/settings.'.$rowKey)">
        {{ __('legacy/settings.text_it_costs_user') ?? '' }}<input type="text" name="{{ $field }}" value="{{ (string)($config[$field] ?? $default) }}">{{ __('legacy/settings.'.$noteKey) }}
    </x-settings-row>
    @endforeach
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_allow_giving_bonus_gift')" name="bonusgift" :value="$config['bonusgift'] ?? 'no'" :note="__('legacy/settings.text_giving_bonus_gift_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-row layout="grid" :label="__('legacy/settings.row_bonus_gift_tax')">
        {{ __('legacy/settings.text_system_charges') ?? '' }}<input type="text" name="basictax" value="{{ (string)($config['basictax'] ?? 5) }}">{{ __('legacy/settings.text_bonus_points_plus') ?? '' }}<input type="text" name="taxpercentage" value="{{ (string)($config['taxpercentage'] ?? 10) }}">{{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_bonus_gift_tax_note')) ?? '' }}
    </x-settings-row>
    <div class="nx-ffull nx-center"><b>{{ __('legacy/settings.text_attendance_get_bonus') ?? 'Attendance bonus' }}</b></div>
    <x-settings-text layout="grid" :label="__('legacy/settings.text_attendance_initial_reward')" name="attendance_initial" :value="$config['attendance_initial'] ?? 0" />
    <x-settings-text layout="grid" :label="__('legacy/settings.text_attendance_continuous_increment')" name="attendance_step" :value="$config['attendance_step'] ?? 0" />
    <x-settings-text layout="grid" :label="__('legacy/settings.text_attendance_reward_limit')" name="attendance_max" :value="$config['attendance_max'] ?? 0" />
    <x-settings-row layout="grid" :label="__('legacy/settings.text_attendance_continuous')">
        <table data-nx="data">
            <tr><td class="colhead">{{ __('legacy/settings.text_attendance_continuous_days') ?? 'Days' }}</td><td class="colhead">{{ __('legacy/settings.text_attendance_continuous_days_additional_reward') ?? 'Reward' }}</td><td class="colhead">{{ __('legacy/settings.text_attendance_continuous_days_action') ?? 'Action' }}</td></tr>
            @foreach (($attendance_continuous ?? []) as $days => $value)
            <tr><td><input type="number" min="0" name="attendance_continuous_day[]" value="{{ $days }}"> {{ __('legacy/settings.text_attendance_continuous_unit') ?? 'days' }}</td><td><input type="number" min="0" name="attendance_continuous_value[]" value="{{ $value }}"> {{ __('legacy/settings.text_attendance_input_suffix') ?? '' }}</td><td><a href="#" class="js-delrow">{{ __('legacy/settings.text_attendance_continuous_item_action_remove') ?? 'Remove' }}</a></td></tr>
            @endforeach
            <tr><td colspan="3">{{ __('legacy/settings.text_attendance_continuous_add_rules') ?? '' }}</td></tr>
            <tr><td><input type="number" min="0" name="attendance_continuous_day[]" value=""> {{ __('legacy/settings.text_attendance_continuous_unit') ?? 'days' }}</td><td><input type="number" min="0" name="attendance_continuous_value[]" value=""> {{ __('legacy/settings.text_attendance_input_suffix') ?? '' }}</td><td><a href="#" class="js-newrow">{{ __('legacy/settings.text_attendance_continuous_item_action_add') ?? 'Add' }}</a></td></tr>
        </table>
    </x-settings-row>
    <x-settings-save layout="grid" :label="__('legacy/settings.row_save_settings')" :text="__('legacy/settings.submit_save_settings')" />
    </form>

@elseif ($action === 'accountsettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_account">@csrf
    <div class="nx-ffull nx-center"><b>{{ __('legacy/settings.text_delete_inactive_accounts') ?? 'Delete inactive' }}</b></div>
    <x-settings-row layout="grid" :label="__('legacy/settings.row_never_delete')">
        {{ \App\Support\UserClass::classSelectWithContext('neverdelete', \App\Enums\UserClass::VIP->value, $config['neverdelete'] ?? 0) }}{{ __('legacy/settings.text_never_delete') ?? '' }}{{ \App\Support\UserClass::name(\App\Enums\UserClass::VETERAN_USER->value, false, true, true) }}
    </x-settings-row>
    <x-settings-row layout="grid" :label="__('legacy/settings.row_never_delete_if_packed')">
        {{ \App\Support\UserClass::classSelectWithContext('neverdeletepacked', \App\Enums\UserClass::VIP->value, $config['neverdeletepacked'] ?? 0) }}{{ __('legacy/settings.text_never_delete_if_packed') ?? '' }}{{ \App\Support\UserClass::name(\App\Enums\UserClass::ELITE_USER->value, false, true, true) }}
    </x-settings-row>
    <x-settings-text layout="grid" :label="__('legacy/settings.row_delete_packed')" name="deletepacked" :value="$config['deletepacked'] ?? 400" :note="__('legacy/settings.text_delete_packed_note_two')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_delete_unpacked')" name="deleteunpacked" :value="$config['deleteunpacked'] ?? 150" :note="__('legacy/settings.text_delete_unpacked_note_two')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_delete_no_transfer')" name="deletenotransfer" :value="$config['deletenotransfer'] ?? 60" :note="__('legacy/settings.text_delete_transfer_note_two')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_destroy_disabled')" name="destroy_disabled" :value="$config['destroy_disabled'] ?? 500" :note="__('legacy/settings.text_destroy_disabled_note_two')" />
    <div class="nx-ffull nx-center"><b>{{ __('legacy/settings.text_user_promotion_demotion') ?? 'Promotion/Demotion' }}</b></div>
    @foreach ([
        [\App\Enums\UserClass::POWER_USER->value, 'pu', 4, 50, 1.05, 0.95, 1],
        [\App\Enums\UserClass::ELITE_USER->value, 'eu', 8, 120, 1.55, 1.45, 0],
        [\App\Enums\UserClass::CRAZY_USER->value, 'cu', 15, 300, 2.05, 1.95, 2],
        [\App\Enums\UserClass::INSANE_USER->value, 'iu', 25, 500, 2.55, 2.45, 0],
        [\App\Enums\UserClass::VETERAN_USER->value, 'vu', 40, 750, 3.05, 2.95, 3],
        [\App\Enums\UserClass::EXTREME_USER->value, 'exu', 60, 1024, 3.55, 3.45, 0],
        [\App\Enums\UserClass::ULTIMATE_USER->value, 'uu', 80, 1536, 4.05, 3.95, 5],
        [\App\Enums\UserClass::NEXUS_MASTER->value, 'nm', 100, 3072, 4.55, 4.45, 10],
    ] as [$class, $prefix, $time, $dl, $prratio, $deratio, $invites])
    <x-settings-row layout="grid" :label="\App\Support\Html\SafeHtml::fromTrustedHtml((__('legacy/settings.row_promote_to_one')).\App\Support\UserClass::name($class, false, false, true).(__('legacy/settings.row_promote_to_two')))">
        {{ __('legacy/settings.text_alias') ?? 'Alias: ' }}<input type="text" name="{{ $class }}_alias" value="{{ (string)($config[$class.'_alias'] ?? '') }}"><br>
        {{ __('legacy/settings.text_member_longer_than') ?? 'Member for ' }}<input type="text" name="{{ $prefix }}time" value="{{ (string)($config[$prefix.'time'] ?? $time) }}">
        {{ __('legacy/settings.text_seed_points_more_than') ?? ' Seed points: ' }}<input type="text" name="{{ $class }}_min_seed_points" value="{{ (string)($config[$class.'_min_seed_points'] ?? 0) }}">
        {{ __('legacy/settings.text_downloaded_more_than') ?? ' Downloaded: ' }}<input type="text" name="{{ $prefix }}dl" value="{{ (string)($config[$prefix.'dl'] ?? $dl) }}">
        {{ __('legacy/settings.text_with_ratio_above') ?? ' Ratio: ' }}<input type="text" name="{{ $prefix }}prratio" value="{{ (string)($config[$prefix.'prratio'] ?? $prratio) }}">
        {{ __('legacy/settings.text_demote_with_ratio_below') ?? ' Demote below: ' }}<input type="text" name="{{ $prefix }}deratio" value="{{ (string)($config[$prefix.'deratio'] ?? $deratio) }}">
        {{ __('legacy/settings.text_users_get') ?? ' Invites: ' }}<input type="text" name="getInvitesByPromotion[{{ $class }}]" value="{{ (string)($config['getInvitesByPromotion'][$class] ?? $invites) }}">
    </x-settings-row>
    @endforeach
    <x-settings-save layout="grid" :label="__('legacy/settings.row_save_settings')" :text="__('legacy/settings.submit_save_settings')" />
    </form>

@elseif ($action === 'torrentsettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_torrent">@csrf
    <x-settings-text layout="grid" :label="__('legacy/settings.row_sticky_first_level_background_color')" name="sticky_first_level_background_color" :value="$config['sticky_first_level_background_color'] ?? ''" :note="__('legacy/settings.text_sticky_first_level_background_color_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_sticky_second_level_background_color')" name="sticky_second_level_background_color" :value="$config['sticky_second_level_background_color'] ?? ''" :note="__('legacy/settings.text_sticky_second_level_background_color_note')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_download_support_passkey')" name="download_support_passkey" :value="$config['download_support_passkey'] ?? 'yes'" :note="__('legacy/settings.text_download_support_passkey_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_approval_status_icon_enabled')" name="approval_status_icon_enabled" :value="$config['approval_status_icon_enabled'] ?? 'no'" :note="__('legacy/settings.text_approval_status_icon_enabled_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_approval_status_none_visible')" name="approval_status_none_visible" :value="$config['approval_status_none_visible'] ?? 'no'" :note="__('legacy/settings.text_approval_status_none_visible_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-radios layout="grid"         :label="__('legacy/settings.row_nfo_view_style_default')"
        name="nfo_view_style_default"
        :options="collect($nfoViewStyles ?? [])->mapWithKeys(fn ($info, $style) => [(string)$style => $info['text'] ?? $style])->all()"
        :selected="(string)($config['nfo_view_style_default'] ?? 0)" />
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_paid_torrent_enabled')" name="paid_torrent_enabled" :value="$config['paid_torrent_enabled'] ?? 'no'" :note="__('legacy/settings.text_paid_torrent_enabled_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.row_tax_factor') ?? 'Tax factor' }}</div><div class="nx-fcell"><input type='number' name=tax_factor value="{{ (string)($config['tax_factor'] ?? 0) }}"> {{ __('legacy/settings.text_tax_factor_note') ?? '' }}</div>
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.row_max_price') ?? 'Max price' }}</div><div class="nx-fcell"><input type='number' name=max_price value="{{ (string)($config['max_price'] ?? 0) }}"> {{ __('legacy/settings.text_max_price_note') ?? '' }}</div>
    <x-settings-text layout="grid" :label="__('legacy/settings.row_reward_bonus_options')" name="reward_bonus_options" :value="$config['reward_bonus_options'] ?? ''" :note="__('legacy/settings.text_reward_bonus_options_note')" />
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.row_reward_times_limit') ?? 'Reward limit' }}</div><div class="nx-fcell"><input type='number' name=reward_times_limit value="{{ (string)($config['reward_times_limit'] ?? 0) }}"> {{ __('legacy/settings.text_reward_times_limit_note') ?? '' }}</div>
    <x-settings-row layout="grid" :label="__('legacy/settings.row_random_promotion')">
        {{ __('legacy/settings.text_random_promotion_note_one') ?? '' }}
        <ul>
            @foreach ([
                ['randomhalfleech', 5, 'text_halfleech_chance_becoming'],
                ['randomfree', 2, 'text_free_chance_becoming'],
                ['randomtwoup', 2, 'text_twoup_chance_becoming'],
                ['randomtwoupfree', 1, 'text_freetwoup_chance_becoming'],
                ['randomtwouphalfdown', 0, 'text_twouphalfleech_chance_becoming'],
                ['randomthirtypercentdown', 0, 'text_thirtypercentleech_chance_becoming'],
            ] as [$field, $default, $noteKey])
                <li><input type="text" name="{{ $field }}" value="{{ (string)($config[$field] ?? $default) }}">{{ __('legacy/settings.'.$noteKey) }}</li>
            @endforeach
        </ul>
        {{ __('legacy/settings.text_random_promotion_note_two') ?? '' }}
    </x-settings-row>
    <x-settings-row layout="grid" :label="__('legacy/settings.row_large_torrent_promotion')">
        {{ __('legacy/settings.text_torrent_larger_than') ?? '' }}<input type="text" name="largesize" value="{{ (string)($config['largesize'] ?? 20) }}">{{ __('legacy/settings.text_gb_promoted_to') ?? '' }}<select name="largepro">{{ $promotionSelects['largepro'] ?? '' }}</select>{{ __('legacy/settings.text_by_system_upon_uploading') ?? '' }}<br>{{ __('legacy/settings.text_large_torrent_promotion_note') ?? '' }}
    </x-settings-row>
    <x-settings-row layout="grid" :label="__('legacy/settings.row_promotion_timeout')">
        {{ __('legacy/settings.text_promotion_timeout_note_one') ?? '' }}
        <ul>
            @foreach ([
                ['halfleechbecome', 'expirehalfleech', 1, 5, 'text_halfleech_will_become', 'text_halfleech_timeout_default', 150],
                ['freebecome', 'expirefree', 1, 2, 'text_free_will_become', 'text_free_timeout_default', 60],
                ['twoupbecome', 'expiretwoup', 1, 3, 'text_twoup_will_become', 'text_twoup_timeout_default', 60],
                ['twoupfreebecome', 'expiretwoupfree', 1, 4, 'text_freetwoup_will_become', 'text_freetwoup_timeout_default', 30],
                ['twouphalfleechbecome', 'expiretwouphalfleech', 1, 6, 'text_halfleechtwoup_will_become', 'text_halfleechtwoup_timeout_default', 30],
                ['thirtypercentleechbecome', 'expirethirtypercentleech', 1, 7, 'text_thirtypercentleech_will_become', 'text_thirtypercentleech_timeout_default', 30],
                ['normalbecome', 'expirenormal', 1, 0, 'text_normal_will_become', 'text_normal_timeout_default', 0],
            ] as [$become, $expire, $defBecome, $hide, $willKey, $defKey, $defExpire])
                <li>{{ __('legacy/settings.'.$willKey) }}<select name="{{ $become }}">{{ $promotionSelects[$become] ?? '' }}</select>{{ __('legacy/settings.text_after') ?? ' after ' }}<input type="text" name="{{ $expire }}" value="{{ (string)($config[$expire] ?? $defExpire) }}">{{ __('legacy/settings.'.$defKey) }}</li>
            @endforeach
        </ul>
        {{ __('legacy/settings.text_promotion_timeout_note_two') ?? '' }}
    </x-settings-row>
    <x-settings-text layout="grid" :label="__('legacy/settings.row_auto_pick_hot')" name="hotdays" :value="$config['hotdays'] ?? 7" :note="__('legacy/settings.text_days_with_more_than')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_auto_pick_hot')" name="hotseeder" :value="$config['hotseeder'] ?? 10" :note="__('legacy/settings.text_be_picked_as_hot')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_uploader_get_double')" name="uploaderdouble" :value="$config['uploaderdouble'] ?? 1" :note="__('legacy/settings.text_times_uploading_credit')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_delete_dead_torrents')" name="deldeadtorrent" :value="$config['deldeadtorrent'] ?? 0" :note="__('legacy/settings.text_days_be_deleted')" />
    <x-settings-save layout="grid" :label="__('legacy/settings.row_save_settings')" :text="__('legacy/settings.submit_save_settings')" />
    </form>

@elseif ($action === 'attachmentsettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_attachment">@csrf
    <x-settings-yesno layout="grid" :label="__('legacy/settings.row_enable_attachment')" name="enableattach" :value="$config['enableattach'] ?? 'no'" :note="__('legacy/settings.text_enable_attachment_note')" :yes-label="__('legacy/settings.text_yes')" :no-label="__('legacy/settings.text_no')" />
    <x-settings-row layout="grid" :label="__('legacy/settings.row_attachment_authority')">
        <ul>
            @foreach ([
                ['one', \App\Enums\UserClass::USER, 'text_authority_default_one_one', 'text_authority_default_one_two'],
                ['two', \App\Enums\UserClass::POWER_USER, 'text_authority_default_two_one', 'text_authority_default_two_two'],
                ['three', \App\Enums\UserClass::ELITE_USER, '', ''],
                ['four', \App\Enums\UserClass::EXTREME_USER, '', ''],
            ] as [$num, $defaultClass, $defOne, $defTwo])
                <li>
                    {{ \App\Support\UserClass::classSelectWithContext('class'.$num, \App\Enums\UserClass::STAFFLEADER->value, $config['class'.$num] ?? 0) }}{{ __('legacy/settings.text_can_upload_at_most') ?? '' }}<input type="text" name="count{{ $num }}" value="{{ (string)($config['count'.$num] ?? '') }}"> {{ __('legacy/settings.text_file_size_below') ?? '' }}<input type="text" name="size{{ $num }}" value="{{ (string)($config['size'.$num] ?? '') }}">{{ __('legacy/settings.text_with_extension_name') ?? '' }}<input type="text" name="ext{{ $num }}" value="{{ (string)($config['ext'.$num] ?? '') }}">{{ __('legacy/settings.'.$defOne) }}{{ \App\Support\UserClass::name($defaultClass->value, false, true, true) }}{{ __('legacy/settings.'.$defTwo) }}
                </li>
            @endforeach
        </ul>
    </x-settings-row>
    <x-settings-text layout="grid" :label="__('legacy/settings.row_save_directory')" name="savedirectory" :value="$config['savedirectory'] ?? './attachments'" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_save_directory_note'))" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_http_directory')" name="httpdirectory" :value="$config['httpdirectory'] ?? 'attachments'" :note="\App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_http_directory_note'))" />
    <x-settings-radios layout="grid"         :label="__('legacy/settings.row_save_directory_type')"
        name="savedirectorytype"
        :options="['onedir' => __('legacy/settings.text_one_directory'), 'monthdir' => __('legacy/settings.text_directories_by_monthes'), 'daydir' => __('legacy/settings.text_directories_by_days')]"
        :selected="$config['savedirectorytype'] ?? 'onedir'"
        :note="__('legacy/settings.text_save_directory_type_note')"
        :break="true" />
    <x-settings-radios layout="grid"         :label="__('legacy/settings.row_image_thumbnails')"
        name="thumbnailtype"
        :options="['no' => __('legacy/settings.text_no_thumbnail'), 'createthumb' => __('legacy/settings.text_create_thumbnail'), 'resizebigimg' => __('legacy/settings.text_resize_big_image')]"
        :selected="$config['thumbnailtype'] ?? 'no'"
        :note="__('legacy/settings.text_image_thumbnail_note')"
        :break="true" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_thumbnail_quality')" name="thumbquality" :value="$config['thumbquality'] ?? 80" :note="__('legacy/settings.text_thumbnail_quality_note')" />
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.row_thumbnail_size') ?? 'Thumb size' }}</div><div class="nx-fcell"><input type='text' name="thumbwidth" value="{{ (string)($config['thumbwidth'] ?? 500) }}"> * <input type='text' name="thumbheight" value="{{ (string)($config['thumbheight'] ?? 500) }}"> {{ \App\Support\Html\SafeHtml::fromUntrustedHtml(__('legacy/settings.text_thumbnail_size_note')) ?? '' }}</div>
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.row_alternative_thumbnail_size') ?? 'Alt thumb size' }}</div><div class="nx-fcell"><input type='text' name="altthumbwidth" value="{{ (string)($config['altthumbwidth'] ?? 180) }}"> * <input type='text' name="altthumbheight" value="{{ (string)($config['altthumbheight'] ?? 135) }}"> {{ __('legacy/settings.text_alternative_thumbnail_size_note') ?? '' }}</div>
    <x-settings-save layout="grid" :label="__('legacy/settings.row_save_settings')" :text="__('legacy/settings.submit_save_settings')" />
    </form>

@elseif ($action === 'codesettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_code">@csrf
    <x-settings-text layout="grid" :label="__('legacy/settings.row_main_version')" name="mainversion" :value="$config['mainversion'] ?? 'NexusPHP'" :note="__('legacy/settings.text_main_version_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_sub_version')" name="subversion" :value="$config['subversion'] ?? '1.0'" :note="__('legacy/settings.text_sub_version_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_release_date')" name="releasedate" :value="$config['releasedate'] ?? '2008-12-10'" :note="__('legacy/settings.text_release_date_note')" />
    <x-settings-text layout="grid" :label="__('legacy/settings.row_web_site')" name="website" :value="$config['website'] ?? ''" :note="__('legacy/settings.text_web_site_note_two')" />
    <x-settings-save layout="grid" :label="__('legacy/settings.row_save_settings')" :text="__('legacy/settings.submit_save_settings')" />
    </form>

@elseif ($action === 'miscsettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_misc">@csrf
    <div class="nx-fhead nx-nowrap">{{ __('legacy/settings.row_misc_donation_custom') ?? 'Donation custom' }}</div><div class="nx-fcell"><textarea cols="100" rows="10" name='donation_custom'>{{ (string)($config['donation_custom'] ?? '') }}</textarea><br>{{ __('legacy/settings.text_donation_custom_note') }}&nbsp;<b><a href="tags.php" target="_blank">{{ __('legacy/settings.text_bbcode_tag') }}</a></b></div>
    <x-settings-text layout="grid" :label="__('legacy/settings.row_protected_forum')" name="protected_forum" :value="$config['protected_forum'] ?? ''" :note="__('legacy/settings.text_protected_forum')" />
    <x-settings-save layout="grid" :label="__('legacy/settings.row_save_settings')" :text="__('legacy/settings.submit_save_settings')" />
    </form>

@endif

</div>
@endsection
