@extends('layouts.legacy')

@section('title', $lang['head_website_settings'] ?? 'Website Settings')

@section('content')
<h1 align="center"><a class="faqlink" href="{{ $scriptName }}">{{ $lang['text_website_settings'] ?? 'Website Settings' }}</a></h1>
<table cellspacing="0" cellpadding="10" width="97%">
<tr><td colspan="2" style="padding: 10px; background: black" align="center">
<font color="white">{{ $lang['text_configuration_file_saving_note'] ?? 'Settings are stored in the database.' }}</font>
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
    <tr><td class="rowhead nowrap" valign="top">{{ $lang[$row] ?? ucfirst($act) }}</td><td>
        <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="{{ $act }}">@csrf
        <input type="submit" value="{{ $lang[$btn] ?? ucfirst($act) }}"> {{ $lang[$note] ?? '' }}
        </form>
    </td></tr>
    @endforeach

@elseif ($action === 'basicsettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_basic">@csrf
    <x-settings-row :label="$lang['row_site_name'] ?? 'Site name'">
        <input type="text" style="width: 300px" name="SITENAME" value="{{ (string)($config['SITENAME'] ?? 'Nexus') }}"> {{ $lang['text_site_name_note'] ?? '' }}
    </x-settings-row>
    <x-settings-row :label="$lang['row_base_url'] ?? 'Base URL'">
        <input type="text" style="width: 300px" name="BASEURL" value="{{ (string)($config['BASEURL'] ?? '') }}"> {{ $lang['text_base_url_note'] ?? '' }}
    </x-settings-row>
    <x-settings-save :label="$lang['row_save_settings'] ?? 'Save'" :text="$lang['submit_save_settings'] ?? 'Save'" />
    </form>

@elseif ($action === 'mainsettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_main">@csrf
    <x-settings-yesno :label="$lang['row_site_online'] ?? 'Site online'" name="site_online" :value="$config['site_online'] ?? 'yes'" :note="$lang['text_site_online_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_enable_invite_system'] ?? 'Invite system'" name="invitesystem" :value="$config['invitesystem'] ?? 'yes'" :note="$lang['text_invite_system_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-text :label="$lang['row_initial_uploading_amount'] ?? 'Initial upload'" name="iniupload" :value="$config['iniupload'] ?? 0" :note="$lang['text_initial_uploading_amount_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_initial_invites'] ?? 'Initial invites'" name="invite_count" :value="$config['invite_count'] ?? 0" :note="$lang['text_initial_invites_note'] ?? ''" width="50px" />
    <x-settings-text :label="$lang['row_initial_tmp_invites'] ?? 'Initial tmp invites'" name="tmp_invite_count" :value="$config['tmp_invite_count'] ?? 0" :note="$lang['text_initial_tmp_invites_note'] ?? ''" width="50px" />
    <x-settings-text :label="$lang['row_invite_timeout'] ?? 'Invite timeout'" name="invite_timeout" :value="$config['invite_timeout'] ?? 0" :note="$lang['text_invite_timeout_note'] ?? ''" width="50px" />
    <x-settings-yesno :label="$lang['row_complain_enabled'] ?? 'Complain'" name="complain_enabled" :value="$config['complain_enabled'] ?? 'no'" :note="$lang['row_complain_enabled_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_enable_registration_system'] ?? 'Registration'" name="registration" :value="$config['registration'] ?? 'yes'" :note="$lang['row_allow_registrations'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-radios
        :label="$lang['row_verification_type'] ?? 'Verification'"
        name="verification"
        :options="['email' => $lang['text_email'] ?? 'email', 'admin' => $lang['text_admin'] ?? 'admin', 'automatic' => $lang['text_automatically'] ?? 'automatic']"
        :selected="$config['verification'] ?? 'email'"
        :note="$lang['text_verification_type_note'] ?? ''" />
    <x-settings-yesno :label="$lang['row_enable_wait_system'] ?? 'Wait system'" name="waitsystem" :value="$config['waitsystem'] ?? 'no'" :note="$lang['text_wait_system_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_enable_max_slots_system'] ?? 'Max slots'" name="maxdlsystem" :value="$config['maxdlsystem'] ?? 'no'" :note="$lang['text_max_slots_system_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_show_polls'] ?? 'Show polls'" name="showpolls" :value="$config['showpolls'] ?? 'yes'" :note="$lang['text_show_polls_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_show_stats'] ?? 'Show stats'" name="showstats" :value="$config['showstats'] ?? 'yes'" :note="$lang['text_show_stats_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_show_last_posts'] ?? 'Show last posts'" name="showlastxforumposts" :value="$config['showlastxforumposts'] ?? 'yes'" :note="$lang['text_show_last_posts_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_show_last_torrents'] ?? 'Show last torrents'" name="showlastxtorrents" :value="$config['showlastxtorrents'] ?? 'yes'" :note="$lang['text_show_last_torrents_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_show_server_load'] ?? 'Show server load'" name="showtrackerload" :value="$config['showtrackerload'] ?? 'yes'" :note="$lang['text_show_server_load_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_show_forum_stats'] ?? 'Show forum stats'" name="showforumstats" :value="$config['showforumstats'] ?? 'yes'" :note="$lang['text_show_forum_stats_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_show_top_uploader'] ?? 'Show top uploader'" name="show_top_uploader" :value="$config['show_top_uploader'] ?? 'yes'" :note="$lang['text_show_top_uploader_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_enable_nfo'] ?? 'Enable NFO'" name="enablenfo" :value="$config['enablenfo'] ?? 'yes'" :note="$lang['text_enable_nfo_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_enable_technical_info'] ?? 'Technical info'" name="enable_technical_info" :value="$config['enable_technical_info'] ?? 'yes'" :note="$lang['text_enable_technical_info'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_enable_global_search_system'] ?? 'Global search'" name="enable_global_search" :value="$config['enable_global_search'] ?? 'no'" :note="$lang['text_global_search_system_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_show_shoutbox'] ?? 'Show shoutbox'" name="showshoutbox" :value="$config['showshoutbox'] ?? 'yes'" :note="$lang['text_show_shoutbox_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_enable_offer_section'] ?? 'Offer section'" name="showoffer" :value="$config['showoffer'] ?? 'yes'" :note="$lang['text_offer_section_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_show_donation'] ?? 'Donation'" name="donation" :value="$config['donation'] ?? 'no'" :note="$lang['text_show_donation_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_weekend_free_uploading'] ?? 'Weekend free'" name="sptime" :value="$config['sptime'] ?? 'no'" :note="$lang['text_weekend_free_uploading_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_enable_bitbucket'] ?? 'Bitbucket'" name="enablebitbucket" :value="$config['enablebitbucket'] ?? 'no'" :note="$lang['text_bitbucket_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_ptshow_naming_style'] ?? 'PTShow naming'" name="altname" :value="$config['altname'] ?? 'no'" :note="$lang['text_ptshow_naming_style_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-radios
        :label="$lang['row_torrents_category_mode'] ?? 'Category mode'"
        name="browsecat"
        :options="collect($searchboxes ?? [])->mapWithKeys(fn ($sb) => [(string)((array)$sb)['id'] => ((array)$sb)['name']])->all()"
        :selected="(string)($config['browsecat'] ?? 0)"
        :note="$lang['text_torrents_category_mode_note'] ?? ''" />
    <x-settings-checkboxes
        :label="$lang['row_site_language_enabled'] ?? 'Site languages'"
        name="site_language_enabled[]"
        :options="collect($allSiteLanguages ?? [])->map(fn ($l) => ['value' => $l->site_lang_folder, 'label' => $l->lang_name, 'checked' => in_array($l->site_lang_folder, $allEnabledLangs ?? [])])->all()"
        :note="$lang['text_site_language_enabled_note'] ?? ''" />
    <x-settings-radios
        :label="$lang['row_default_site_language'] ?? 'Default language'"
        name="defaultlang"
        :options="collect($allSiteLanguages ?? [])->map(fn ($l) => ['value' => $l->site_lang_folder, 'label' => $l->lang_name, 'disabled' => ! in_array($l->site_lang_folder, $allEnabledLangs ?? [])])->all()"
        :selected="(string)($config['defaultlang'] ?? '')"
        :note="$lang['text_default_site_language_note'] ?? ''" />
    <x-settings-select
        :label="$lang['row_default_stylesheet'] ?? 'Stylesheet'"
        name="defstylesheet"
        :options="collect($stylesheets ?? [])->mapWithKeys(fn ($ss) => [(string)((array)$ss)['id'] => ((array)$ss)['name']])->all()"
        :selected="(string)($config['defstylesheet'] ?? 0)"
        :note="$lang['text_default_stylesheet_note'] ?? ''" />
    <x-settings-text :label="$lang['row_site_logo'] ?? 'Logo'" name="logo" :value="$config['logo'] ?? ''" :note="$lang['text_site_logo_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_max_torrent_size'] ?? 'Max torrent size'" name="max_torrent_size" :value="$config['max_torrent_size'] ?? 1048576" :note="$lang['text_max_torrent_size_note'] ?? ''" width="100px" />
    <x-settings-row :label="$lang['row_announce_interval'] ?? 'Announce interval'">
        {{ $lang['text_announce_interval_note_one'] ?? '' }}<br>
        <ul>
            <li>{{ $lang['text_announce_default'] ?? '' }}<input type="text" style="width: 100px" name="announce_interval" value="{{ (string)($config['announce_interval'] ?? 1800) }}"> {{ $lang['text_announce_default_default'] ?? '' }}</li>
            <li>{{ $lang['text_for_torrents_older_than'] ?? '' }}<input type="text" style="width: 100px" name="annintertwoage" value="{{ (string)($config['annintertwoage'] ?? 7) }}">{{ $lang['text_days'] ?? 'days' }}<input type="text" style="width: 100px" name="annintertwo" value="{{ (string)($config['annintertwo'] ?? 2700) }}"> {{ $lang['text_announce_two_default'] ?? '' }}</li>
            <li>{{ $lang['text_for_torrents_older_than'] ?? '' }}<input type="text" style="width: 100px" name="anninterthreeage" value="{{ (string)($config['anninterthreeage'] ?? 30) }}">{{ $lang['text_days'] ?? 'days' }}<input type="text" style="width: 100px" name="anninterthree" value="{{ (string)($config['anninterthree'] ?? 3600) }}"> {{ $lang['text_announce_three_default'] ?? '' }}</li>
        </ul>
        {{ $lang['text_announce_interval_note_two'] ?? '' }}
    </x-settings-row>
    <x-settings-row :label="$lang['row_cleanup_interval'] ?? 'Cleanup interval'">
        {{ $lang['text_cleanup_interval_note_one'] ?? '' }}<br>
        <ul>
            @foreach (['one' => 'autoclean_interval_one', 'two' => 'autoclean_interval_two', 'three' => 'autoclean_interval_three', 'four' => 'autoclean_interval_four', 'five' => 'autoclean_interval_five'] as $pri => $field)
                <li>{{ $lang["text_priority_{$pri}"] ?? '' }}<input type="text" style="width: 100px" name="{{ $field }}" value="{{ (string)($config[$field] ?? '') }}"> {{ $lang["text_priority_{$pri}_note"] ?? '' }}</li>
            @endforeach
        </ul>
        {{ $lang['text_cleanup_interval_note_two'] ?? '' }}
    </x-settings-row>
    <x-settings-text :label="$lang['row_signup_timeout'] ?? 'Signup timeout'" name="signup_timeout" :value="$config['signup_timeout'] ?? 259200" :note="$lang['text_signup_timeout_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_min_offer_votes'] ?? 'Min offer votes'" name="minoffervotes" :value="$config['minoffervotes'] ?? 15" :note="$lang['text_min_offer_votes_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_offer_vote_timeout'] ?? 'Offer vote timeout'" name="offervotetimeout" :value="$config['offervotetimeout'] ?? 259200" :note="$lang['text_offer_vote_timeout_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_offer_upload_timeout'] ?? 'Offer upload timeout'" name="offeruptimeout" :value="$config['offeruptimeout'] ?? 86400" :note="$lang['text_offer_upload_timeout_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_offer_skip_approved_count'] ?? 'Offer skip approved'" name="offer_skip_approved_count" :value="$config['offer_skip_approved_count'] ?? ''" :note="$lang['text_offer_skip_approved_count_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_upload_deny_approval_deny_count'] ?? 'Upload deny count'" name="upload_deny_approval_deny_count" :value="$config['upload_deny_approval_deny_count'] ?? ''" :note="$lang['text_upload_deny_approval_deny_count_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_max_subtitle_size'] ?? 'Max subtitle size'" name="maxsubsize" :value="$config['maxsubsize'] ?? 3145728" :note="$lang['text_max_subtitle_size_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_posts_per_page'] ?? 'Posts per page'" name="postsperpage" :value="$config['postsperpage'] ?? 10" :note="$lang['text_posts_per_page_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_topics_per_page'] ?? 'Topics per page'" name="topicsperpage" :value="$config['topicsperpage'] ?? 20" :note="$lang['text_topics_per_page_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_torrents_per_page'] ?? 'Torrents per page'" name="torrentsperpage" :value="$config['torrentsperpage'] ?? 50" :note="$lang['text_torrents_per_page_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_number_of_news'] ?? 'News count'" name="maxnewsnum" :value="$config['maxnewsnum'] ?? 3" :note="$lang['text_number_of_news_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_torrent_dead_time'] ?? 'Dead torrent time'" name="max_dead_torrent_time" :value="$config['max_dead_torrent_time'] ?? 21600" :note="$lang['text_torrent_dead_time_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_max_users'] ?? 'Max users'" name="maxusers" :value="$config['maxusers'] ?? 2500" :note="$lang['text_max_users'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_site_accountant_userid'] ?? 'Accountant ID'" name="ACCOUNTANTID" :value="$config['ACCOUNTANTID'] ?? ''" :note="$lang['text_site_accountant_userid_note'] ?? ''" width="200px" />
    <x-settings-text :label="$lang['row_alipay_account'] ?? 'Alipay'" name="ALIPAYACCOUNT" :value="$config['ALIPAYACCOUNT'] ?? ''" :note="$lang['text_alipal_account_note'] ?? ''" width="200px" />
    <x-settings-text :label="$lang['row_paypal_account'] ?? 'PayPal'" name="PAYPALACCOUNT" :value="$config['PAYPALACCOUNT'] ?? ''" :note="$lang['text_paypal_account_note'] ?? ''" width="200px" />
    <x-settings-text :label="$lang['row_site_email'] ?? 'Site email'" name="SITEEMAIL" :value="$config['SITEEMAIL'] ?? ''" :note="$lang['text_site_email_note'] ?? ''" width="200px" />
    <x-settings-text :label="$lang['row_report_email'] ?? 'Report email'" name="reportemail" :value="$config['reportemail'] ?? ''" :note="$lang['text_report_email_note'] ?? ''" width="200px" />
    <x-settings-text :label="$lang['row_site_slogan'] ?? 'Slogan'" name="SLOGAN" :value="$config['SLOGAN'] ?? ''" :note="$lang['text_site_slogan_note'] ?? ''" width="300px" />
    <x-settings-text :label="$lang['row_icp_license'] ?? 'ICP license'" name="icplicense" :value="$config['icplicense'] ?? ''" :note="$lang['text_icp_license_note'] ?? ''" width="300px" />
    <x-settings-text :label="$lang['row_torrent_directory'] ?? 'Torrent dir'" name="torrent_dir" :value="$config['torrent_dir'] ?? 'torrents'" :note="$lang['text_torrent_directory'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_bitbucket_directory'] ?? 'Bitbucket dir'" name="bitbucket" :value="$config['bitbucket'] ?? 'bitbucket'" :note="$lang['text_bitbucket_directory_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_torrent_name_prefix'] ?? 'Name prefix'" name="torrentnameprefix" :value="$config['torrentnameprefix'] ?? '[Nexus]'" :note="$lang['text_torrent_name_prefix_note'] ?? ''" width="100px" />
    <x-settings-save :label="$lang['row_save_settings'] ?? 'Save'" :text="$lang['submit_save_settings'] ?? 'Save'" />
    </form>

@elseif ($action === 'smtpsettings')
    <form method="post" action="{{ $scriptName }}" name="smtpsettings_form"><input type="hidden" name="action" value="savesettings_smtp">@csrf
    <x-settings-yesno :label="$lang['row_enable_email_notification'] ?? 'Email notify'" name="emailnotify" :value="$config['emailnotify'] ?? 'no'" :note="$lang['text_email_notification_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-radios
        :label="$lang['row_mail_function_type'] ?? 'Mail type'"
        name="smtptype"
        :options="['default' => $lang['text_smtp_default'] ?? 'default', 'advanced' => $lang['text_smtp_advanced'] ?? 'advanced', 'external' => $lang['text_smtp_external'] ?? 'external', 'none' => $lang['text_smtp_none'] ?? 'none']"
        :selected="$config['smtptype'] ?? 'default'"
        :break="true" />
    <tbody id="smtp_advanced"@if(($config['smtptype'] ?? 'default') !== 'advanced') class="nx-hidden"@endif>
    <tr><td colspan=2 align=center><b>{{ $lang['text_setting_for_advanced_type'] ?? 'Advanced' }}</b></td></tr>
    <x-settings-text :label="$lang['row_smtp_host'] ?? 'SMTP host'" name="smtp_host" :value="$config['smtp_host'] ?? 'localhost'" :note="$lang['text_smtp_host_note'] ?? ''" width="300px" />
    <x-settings-text :label="$lang['row_smtp_port'] ?? 'SMTP port'" name="smtp_port" :value="$config['smtp_port'] ?? 25" :note="$lang['text_smtp_port_note'] ?? ''" width="300px" />
    <x-settings-text :label="$lang['row_smtp_sendmail_from'] ?? 'Sendmail from'" name="smtp_from" :value="$config['smtp_from'] ?? ''" :note="$lang['text_smtp_sendmail_from_note'] ?? ''" width="300px" />
    </tbody>
    <tbody id="smtp_external"@if(($config['smtptype'] ?? 'default') !== 'external') class="nx-hidden"@endif>
    <tr><td colspan=2 align=center><b>{{ $lang['text_setting_for_external_type'] ?? 'External' }}</b></td></tr>
    <x-settings-text :label="$lang['row_outgoing_mail_address'] ?? 'SMTP address'" name="smtpaddress" :value="$config['smtpaddress'] ?? ''" :note="$lang['text_outgoing_mail_address_note'] ?? ''" width="300px" />
    <x-settings-text :label="$lang['row_outgoing_mail_port'] ?? 'SMTP port'" name="smtpport" :value="$config['smtpport'] ?? ''" :note="$lang['text_outgoing_mail_port_note'] ?? ''" width="300px" />
    <x-settings-radios
        :label="$lang['row_outgoing_mail_encryption'] ?? 'Encryption'"
        name="encryption"
        :options="['' => 'none', 'tls' => 'tls', 'ssl' => 'ssl']"
        :selected="(string)($config['encryption'] ?? '')" />
    <x-settings-text :label="$lang['row_smtp_account_name'] ?? 'Account name'" name="accountname" :value="$config['accountname'] ?? ''" :note="$lang['text_smtp_account_name_note'] ?? ''" width="300px" />
    <tr><td class="rowhead nowrap">{{ $lang['row_smtp_account_password'] ?? 'Password' }}</td><td><input type=password name=accountpassword style="width: 300px" value="{{ (string)($config['accountpassword'] ?? '') }}"> @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_smtp_account_password_note'] ?? ''))</td></tr>
    </tbody>
    <x-settings-save :label="$lang['row_save_settings'] ?? 'Save'" :text="$lang['submit_save_settings'] ?? 'Save'" />
    </form>

@elseif ($action === 'securitysettings')
    <form method="post" action="{{ $scriptName }}" name="securitysettings_form"><input type="hidden" name="action" value="savesettings_security">@csrf
    <x-settings-radios
        :label="$lang['row_enable_ssl'] ?? 'SSL'"
        name="securelogin"
        :options="['yes' => $lang['text_yes'] ?? 'yes', 'no' => $lang['text_no'] ?? 'no', 'op' => $lang['text_optional'] ?? 'op']"
        :selected="$config['securelogin'] ?? 'no'"
        :note="$lang['text_ssl_note'] ?? ''" />
    <x-settings-yesno :label="$lang['row_enable_image_verification'] ?? 'Image verification'" name="iv" :value="$config['iv'] ?? 'yes'" :note="$lang['text_image_verification_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_allow_email_change'] ?? 'Email change'" name="changeemail" :value="$config['changeemail'] ?? 'yes'" :note="$lang['text_email_change_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-row :label="$lang['row_cheater_detection_level'] ?? 'Cheater detection'">
        <select name="cheaterdet">
            @foreach (['0' => 'select_none', '1' => 'select_conservative', '2' => 'select_normal', '3' => 'select_strict', '4' => 'select_paranoid'] as $val => $labelKey)
                <option value="{{ $val }}"@if ((string)($config['cheaterdet'] ?? 0) === $val) selected @endif> {{ $lang[$labelKey] ?? $val }} </option>
            @endforeach
        </select> {{ $lang['text_cheater_detection_level_note'] ?? '' }}<br>
        {{ $lang['text_never_suspect'] ?? '' }}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserClass::classSelectWithContext('nodetect', (int)($authority['staffmem'] ?? 0), $config['nodetect'] ?? 0))){{ $lang['text_or_above'] ?? '' }}
    </x-settings-row>
    <x-settings-text :label="$lang['row_max_ips'] ?? 'Max IPs'" name="maxip" :value="$config['maxip'] ?? 1" :note="$lang['text_max_ips_note'] ?? ''" width="300px" />
    <x-settings-text :label="$lang['row_max_login_attemps'] ?? 'Max login attempts'" name="maxloginattempts" :value="$config['maxloginattempts'] ?? 7" :note="$lang['text_max_login_attemps_note'] ?? ''" width="300px" />
    <x-settings-yesno :label="$lang['row_use_challenge_response_authentication'] ?? 'Challenge response'" name="use_challenge_response_authentication" :value="$config['use_challenge_response_authentication'] ?? 'no'" :note="$lang['text_use_challenge_response_authentication_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-radios
        :label="$lang['row_guest_visit_type'] ?? 'Guest visit'"
        name="guest_visit_type"
        :options="['normal' => $lang['text_guest_visit_type_normal'] ?? 'normal', 'static_page' => $lang['text_guest_visit_type_static_page'] ?? 'static_page', 'custom_content' => $lang['text_guest_visit_type_custom_content'] ?? 'custom_content', 'redirect' => $lang['text_guest_visit_type_redirect'] ?? 'redirect']"
        :selected="$config['guest_visit_type'] ?? 'normal'"
        :break="true" />
    <tbody id="tbody_static_page"@if(($config['guest_visit_type'] ?? '') !== 'static_page') class="nx-hidden"@endif>
    <x-settings-select
        :label="$lang['row_guest_visit_value_static_page'] ?? 'Static page'"
        name="guest_visit_value_static_page"
        :options="collect($staticPages ?? [])->mapWithKeys(fn ($p) => [$p => $p])->all()"
        :selected="(string)($config['guest_visit_value_static_page'] ?? '')"
        :note="$lang['text_guest_visit_value_static_page'] ?? ''" />
    </tbody>
    <tbody id="tbody_custom_content"@if(($config['guest_visit_type'] ?? '') !== 'custom_content') class="nx-hidden"@endif>
    <tr><td class="rowhead nowrap" valign="top">{{ $lang['row_guest_visit_value_custom_content'] ?? 'Custom content' }}</td><td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Form::bbcodeEditor('securitysettings_form', 'guest_visit_value_custom_content', $config['guest_visit_value_custom_content'] ?? '')))</td></tr>
    </tbody>
    <tbody id="tbody_redirect"@if(($config['guest_visit_type'] ?? '') !== 'redirect') class="nx-hidden"@endif>
    <x-settings-text :label="$lang['row_guest_visit_value_redirect'] ?? 'Redirect URL'" name="guest_visit_value_redirect" :value="$config['guest_visit_value_redirect'] ?? ''" width="300px" />
    </tbody>
    <x-settings-row :label="$lang['row_login_type'] ?? 'Login type'">
        @foreach (['normal' => 'text_login_type_normal', 'secret' => 'text_login_type_secret', 'passkey' => 'text_login_type_passkey'] as $val => $labelKey)
            <label><input type="radio" name="login_type" value="{{ $val }}"@if (($config['login_type'] ?? 'normal') === $val) checked @endif>{{ $lang[$labelKey] ?? $val }}</label>
        @endforeach
        <b style="color: #DC143C; margin-left: 20px">{{ $lang['text_login_type_warning'] ?? '' }}</b>
    </x-settings-row>
    <tbody id="tbody_login_secret"@if(!in_array($config['login_type'] ?? '', ['secret', 'passkey'])) class="nx-hidden"@endif>
    <x-settings-row :label="$lang['row_login_secret'] ?? 'Login secret'">
        {{ $lang['text_login_secret_current'] ?? 'Current secret' }}：{{ $config['login_secret'] ?? '' }}
        @if (! empty($config['login_secret']))
            <br>{{ $lang['text_login_url_with_secret'] ?? '' }}: {{ \App\Support\Url::schemeAndHost(false) }}/login.php?secret={{ $config['login_secret'] }}
        @endif
        <br><label><input type="radio" name="login_secret_regenerate" value="no"@if (! empty($config['login_secret'])) checked @endif>{{ $lang['text_login_secret_regenerate_no'] ?? 'No' }}</label>
        <br><label><input type="radio" name="login_secret_regenerate" value="yes"@if (empty($config['login_secret'])) checked @endif>{{ $lang['text_login_secret_regenerate_yes'] ?? 'Yes' }}</label>
    </x-settings-row>
    <tr><td class="rowhead nowrap">{{ $lang['row_login_secret_lifetime'] ?? 'Secret lifetime' }}</td><td><input type="text" name="login_secret_lifetime" value="{{ (string)($config['login_secret_lifetime'] ?? '') }}">{{ $lang['text_login_secret_lifetime_unit'] ?? ' min' }}</td></tr>
    </tbody>
    <x-settings-save :label="$lang['row_save_settings'] ?? 'Save'" :text="$lang['submit_save_settings'] ?? 'Save'" />
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
    <x-settings-row :label="\App\Support\Html\SafeHtml::fromTrustedHtml((string) ($rowKey !== null ? ($lang[$rowKey] ?? ucfirst($perm)) : \App\Support\Locale::trans('permission.'.$perm.'.text', [], null)))">
        {{ $lang['text_minimum_class'] ?? 'Min class: ' }}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserClass::classSelectWithContext($perm, \App\Enums\UserClass::SYSOP->value, $config[$perm] ?? 0, 0, true))){{ $lang['text_default'] ?? ' Default: ' }}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserClass::name($defaultClass->value, false, true, true))){{ $noteKey !== null ? ($lang[$noteKey] ?? '') : \App\Support\Locale::trans('permission.'.$perm.'.desc', [], null) }}
    </x-settings-row>
    @endforeach
    <x-settings-save :label="$lang['row_save_settings'] ?? 'Save'" :text="$lang['submit_save_settings'] ?? 'Save'" />
    </form>

@elseif ($action === 'tweaksettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_tweak">@csrf
    <x-settings-yesno :label="$lang['row_save_user_location'] ?? 'Save location'" name="where" :value="$config['where'] ?? 'no'" :note="$lang['text_save_user_location_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-row :label="$lang['row_kps_enabled'] ?? 'Bonus enabled'">
        @foreach (['enable' => 'text_enabled', 'disablesave' => 'text_disabled_but_save', 'disable' => 'text_disabled_no_save'] as $val => $labelKey)
            <input type="radio" id="bonus{{ $val }}" name="bonus" value="{{ $val }}"@if (($config['bonus'] ?? 'enable') === $val) checked @endif> <label for="bonus{{ $val }}">{{ $lang[$labelKey] ?? $val }}</label>
        @endforeach
        <br>{{ $lang['text_kps_note'] ?? '' }}
    </x-settings-row>
    <x-settings-yesno :label="$lang['row_enable_location'] ?? 'Enable location'" name="enablelocation" :value="$config['enablelocation'] ?? 'no'" :note="$lang['text_enable_location_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_enable_tooltip'] ?? 'Enable tooltip'" name="enabletooltip" :value="$config['enabletooltip'] ?? 'no'" :note="$lang['text_enable_tooltip_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-text :label="$lang['row_title_keywords'] ?? 'Title keywords'" name="titlekeywords" :value="$config['titlekeywords'] ?? ''" :note="$lang['text_title_keywords_note'] ?? ''" width="300px" />
    <x-settings-text :label="$lang['row_meta_keywords'] ?? 'Meta keywords'" name="metakeywords" :value="$config['metakeywords'] ?? ''" :note="$lang['text_meta_keywords_note'] ?? ''" width="300px" />
    <tr><td class="rowhead nowrap" valign="top">{{ $lang['row_meta_description'] ?? 'Meta description' }}</td><td><textarea cols="100" style="width: 450px;" rows="5" name='metadescription'>{{ (string)($config['metadescription'] ?? '') }}</textarea><br>{{ $lang['text_meta_description_note'] ?? '' }}</td></tr>
    <tr><td class="rowhead nowrap" valign="top">{{ $lang['row_web_analytics_code'] ?? 'Analytics code' }}</td><td><textarea cols="100" style="width: 450px;" rows="5" name='analyticscode'>{{ (string)($config['analyticscode'] ?? '') }}</textarea><br>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_web_analytics_code_note'] ?? ''))</td></tr>
    <tr><td class="rowhead nowrap">{{ $lang['row_see_sql_debug'] ?? 'SQL debug' }}</td><td><input type='checkbox' name='enablesqldebug' value='yes'@if (($config['enablesqldebug'] ?? 'no') === 'yes') checked @endif>{{ $lang['text_allow'] ?? 'Allow' }}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserClass::classSelectWithContext('sqldebug', \App\Enums\UserClass::STAFFLEADER->value, $config['sqldebug'] ?? \App\Enums\UserClass::MODERATOR->value))){{ $lang['text_see_sql_list'] ?? '' }}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserClass::name(\App\Enums\UserClass::SYSOP->value, false, true, true)))</td></tr>
    <x-settings-text :label="$lang['row_tracker_founded_date'] ?? 'Founded date'" name="datefounded" :value="$config['datefounded'] ?? '2007-12-24'" :note="$lang['text_tracker_founded_date_note'] ?? ''" width="300px" />
    <x-settings-text :label="$lang['row_css_date'] ?? 'CSS date'" name="cssdate" :value="$config['cssdate'] ?? ''" :note="$lang['text_css_date'] ?? ''" width="300px" />
    <x-settings-save :label="$lang['row_save_settings'] ?? 'Save'" :text="$lang['submit_save_settings'] ?? 'Save'" />
    </form>

@elseif ($action === 'bonussettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_bonus">@csrf
    <tr><td colspan=2 align=center><b>{{ $lang['text_bonus_by_seeding'] ?? 'Bonus by seeding' }}</b></td></tr>
    <x-settings-text :label="$lang['row_min_size'] ?? 'Min size'" name="min_size" :value="$config['min_size'] ?? 0" :note="$lang['text_bonus_mini_size'] ?? (''.($lang['text_bonus_mini_size_help'] ?? ''))" width="100px" />
    <x-settings-text :label="$lang['row_donor_gets_double'] ?? 'Donor double'" name="donortimes" :value="$config['donortimes'] ?? 2" :note="$lang['text_donor_gets'] ?? (''.($lang['text_times_as_many'] ?? ''))" width="50px" />
    <x-settings-row :label="$lang['row_basic_seeding_bonus'] ?? 'Basic seeding'">
        {{ $lang['text_user_would_get'] ?? '' }}<input type="text" style="width: 50px" name="perseeding" value="{{ (string)($config['perseeding'] ?? 1) }}">{{ $lang['text_bonus_points'] ?? '' }}<input type="text" style="width: 50px" name="maxseeding" value="{{ (string)($config['maxseeding'] ?? 7) }}">{{ $lang['text_torrents_default'] ?? '' }}
    </x-settings-row>
    <tr><td colspan=2 align=center><b>{{ $lang['text_misc_ways_get_bonus'] ?? 'Misc bonus' }}</b></td></tr>
    @foreach ([
        ['uploadtorrent', 'row_uploading_torrent', 15, 'text_uploading_torrent_note'],
        ['starttopic', 'row_starting_topic', 2, 'text_starting_topic_note'],
        ['makepost', 'row_making_post', 1, 'text_making_post_note'],
        ['addcomment', 'row_adding_comment', 1, 'text_adding_comment_note'],
        ['pollvote', 'row_voting_on_poll', 1, 'text_voting_on_poll_note'],
        ['offervote', 'row_voting_on_offer', 1, 'text_voting_on_offer_note'],
    ] as [$field, $rowKey, $default, $noteKey])
    <x-settings-row :label="$lang[$rowKey] ?? ucfirst($field)">
        {{ $lang['text_user_would_get'] ?? '' }}<input type="text" style="width: 50px" name="{{ $field }}" value="{{ (string)($config[$field] ?? $default) }}">{{ $lang[$noteKey] ?? '' }}
    </x-settings-row>
    @endforeach
    <x-settings-row :label="$lang['row_saying_thanks'] ?? 'Thanks'">
        {{ $lang['text_giver_and_receiver_get'] ?? '' }}<input type="text" style="width: 50px" name="saythanks" value="{{ (string)($config['saythanks'] ?? 0.5) }}">{{ $lang['text_saying_thanks_and'] ?? '' }}<input type="text" style="width: 50px" name="receivethanks" value="{{ (string)($config['receivethanks'] ?? 0) }}">{{ $lang['text_saying_thanks_default'] ?? '' }}
    </x-settings-row>
    <tr><td colspan=2 align=center><b>{{ $lang['text_things_cost_bonus'] ?? 'Things that cost bonus' }}</b></td></tr>
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
    <x-settings-row :label="$lang[$rowKey] ?? ucfirst($field)">
        {{ $lang['text_it_costs_user'] ?? '' }}<input type="text" style="width: 50px" name="{{ $field }}" value="{{ (string)($config[$field] ?? $default) }}">{{ $lang[$noteKey] ?? '' }}
    </x-settings-row>
    @endforeach
    <x-settings-yesno :label="$lang['row_allow_giving_bonus_gift'] ?? 'Bonus gift'" name="bonusgift" :value="$config['bonusgift'] ?? 'no'" :note="$lang['text_giving_bonus_gift_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-row :label="$lang['row_bonus_gift_tax'] ?? 'Gift tax'">
        {{ $lang['text_system_charges'] ?? '' }}<input type="text" style="width: 50px" name="basictax" value="{{ (string)($config['basictax'] ?? 5) }}">{{ $lang['text_bonus_points_plus'] ?? '' }}<input type="text" style="width: 50px" name="taxpercentage" value="{{ (string)($config['taxpercentage'] ?? 10) }}">{{ $lang['text_bonus_gift_tax_note'] ?? '' }}
    </x-settings-row>
    <tr><td colspan="2" align="center"><b>{{ $lang['text_attendance_get_bonus'] ?? 'Attendance bonus' }}</b></td></tr>
    <x-settings-text :label="$lang['text_attendance_initial_reward'] ?? 'Initial reward'" name="attendance_initial" :value="$config['attendance_initial'] ?? 0" width="30px" />
    <x-settings-text :label="$lang['text_attendance_continuous_increment'] ?? 'Step'" name="attendance_step" :value="$config['attendance_step'] ?? 0" width="30px" />
    <x-settings-text :label="$lang['text_attendance_reward_limit'] ?? 'Max'" name="attendance_max" :value="$config['attendance_max'] ?? 0" width="50px" />
    <x-settings-row :label="$lang['text_attendance_continuous'] ?? 'Continuous'">
        <table>
            <tr><td class="colhead">{{ $lang['text_attendance_continuous_days'] ?? 'Days' }}</td><td class="colhead">{{ $lang['text_attendance_continuous_days_additional_reward'] ?? 'Reward' }}</td><td class="colhead">{{ $lang['text_attendance_continuous_days_action'] ?? 'Action' }}</td></tr>
            @foreach (($attendance_continuous ?? []) as $days => $value)
            <tr><td><input type="number" min="0" style="width: 40px" name="attendance_continuous_day[]" value="{{ $days }}"> {{ $lang['text_attendance_continuous_unit'] ?? 'days' }}</td><td><input type="number" min="0" style="width: 50px;" name="attendance_continuous_value[]" value="{{ $value }}"> {{ $lang['text_attendance_input_suffix'] ?? '' }}</td><td><a href="#" class="js-delrow">{{ $lang['text_attendance_continuous_item_action_remove'] ?? 'Remove' }}</a></td></tr>
            @endforeach
            <tr><td colspan="3">{{ $lang['text_attendance_continuous_add_rules'] ?? '' }}</td></tr>
            <tr><td><input type="number" min="0" style="width: 40px" name="attendance_continuous_day[]" value=""> {{ $lang['text_attendance_continuous_unit'] ?? 'days' }}</td><td><input type="number" min="0" style="width: 50px;" name="attendance_continuous_value[]" value=""> {{ $lang['text_attendance_input_suffix'] ?? '' }}</td><td><a href="#" class="js-newrow">{{ $lang['text_attendance_continuous_item_action_add'] ?? 'Add' }}</a></td></tr>
        </table>
    </x-settings-row>
    <x-settings-save :label="$lang['row_save_settings'] ?? 'Save'" :text="$lang['submit_save_settings'] ?? 'Save'" />
    </form>

@elseif ($action === 'accountsettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_account">@csrf
    <tr><td colspan=2 align=center><b>{{ $lang['text_delete_inactive_accounts'] ?? 'Delete inactive' }}</b></td></tr>
    <x-settings-row :label="$lang['row_never_delete'] ?? 'Never delete'">
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserClass::classSelectWithContext('neverdelete', \App\Enums\UserClass::VIP->value, $config['neverdelete'] ?? 0))){{ $lang['text_never_delete'] ?? '' }}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserClass::name(\App\Enums\UserClass::VETERAN_USER->value, false, true, true)))
    </x-settings-row>
    <x-settings-row :label="$lang['row_never_delete_if_packed'] ?? 'Never delete packed'">
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserClass::classSelectWithContext('neverdeletepacked', \App\Enums\UserClass::VIP->value, $config['neverdeletepacked'] ?? 0))){{ $lang['text_never_delete_if_packed'] ?? '' }}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserClass::name(\App\Enums\UserClass::ELITE_USER->value, false, true, true)))
    </x-settings-row>
    <x-settings-text :label="$lang['row_delete_packed'] ?? 'Delete packed'" name="deletepacked" :value="$config['deletepacked'] ?? 400" :note="$lang['text_delete_packed_note_two'] ?? ''" width="50px" />
    <x-settings-text :label="$lang['row_delete_unpacked'] ?? 'Delete unpacked'" name="deleteunpacked" :value="$config['deleteunpacked'] ?? 150" :note="$lang['text_delete_unpacked_note_two'] ?? ''" width="50px" />
    <x-settings-text :label="$lang['row_delete_no_transfer'] ?? 'Delete no transfer'" name="deletenotransfer" :value="$config['deletenotransfer'] ?? 60" :note="$lang['text_delete_transfer_note_two'] ?? ''" width="50px" />
    <x-settings-text :label="$lang['row_destroy_disabled'] ?? 'Destroy disabled'" name="destroy_disabled" :value="$config['destroy_disabled'] ?? 500" :note="$lang['text_destroy_disabled_note_two'] ?? ''" width="50px" />
    <tr><td colspan=2 align=center><b>{{ $lang['text_user_promotion_demotion'] ?? 'Promotion/Demotion' }}</b></td></tr>
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
    <x-settings-row :label="\App\Support\Html\SafeHtml::fromTrustedHtml(($lang['row_promote_to_one'] ?? 'Promote to ').\App\Support\UserClass::name($class, false, false, true).($lang['row_promote_to_two'] ?? ''))">
        {{ $lang['text_alias'] ?? 'Alias: ' }}<input type="text" style="width: 60px" name="{{ $class }}_alias" value="{{ (string)($config[$class.'_alias'] ?? '') }}"><br>
        {{ $lang['text_member_longer_than'] ?? 'Member for ' }}<input type="text" style="width: 50px" name="{{ $prefix }}time" value="{{ (string)($config[$prefix.'time'] ?? $time) }}">
        {{ $lang['text_seed_points_more_than'] ?? ' Seed points: ' }}<input type="text" style="width: 60px" name="{{ $class }}_min_seed_points" value="{{ (string)($config[$class.'_min_seed_points'] ?? 0) }}">
        {{ $lang['text_downloaded_more_than'] ?? ' Downloaded: ' }}<input type="text" style="width: 50px" name="{{ $prefix }}dl" value="{{ (string)($config[$prefix.'dl'] ?? $dl) }}">
        {{ $lang['text_with_ratio_above'] ?? ' Ratio: ' }}<input type="text" style="width: 50px" name="{{ $prefix }}prratio" value="{{ (string)($config[$prefix.'prratio'] ?? $prratio) }}">
        {{ $lang['text_demote_with_ratio_below'] ?? ' Demote below: ' }}<input type="text" style="width: 50px" name="{{ $prefix }}deratio" value="{{ (string)($config[$prefix.'deratio'] ?? $deratio) }}">
        {{ $lang['text_users_get'] ?? ' Invites: ' }}<input type="text" style="width: 50px" name="getInvitesByPromotion[{{ $class }}]" value="{{ (string)($config['getInvitesByPromotion'][$class] ?? $invites) }}">
    </x-settings-row>
    @endforeach
    <x-settings-save :label="$lang['row_save_settings'] ?? 'Save'" :text="$lang['submit_save_settings'] ?? 'Save'" />
    </form>

@elseif ($action === 'torrentsettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_torrent">@csrf
    <x-settings-text :label="$lang['row_sticky_first_level_background_color'] ?? 'Sticky 1st color'" name="sticky_first_level_background_color" :value="$config['sticky_first_level_background_color'] ?? ''" :note="$lang['text_sticky_first_level_background_color_note'] ?? ''" width="100px" />
    <x-settings-text :label="$lang['row_sticky_second_level_background_color'] ?? 'Sticky 2nd color'" name="sticky_second_level_background_color" :value="$config['sticky_second_level_background_color'] ?? ''" :note="$lang['text_sticky_second_level_background_color_note'] ?? ''" width="100px" />
    <x-settings-yesno :label="$lang['row_download_support_passkey'] ?? 'Passkey download'" name="download_support_passkey" :value="$config['download_support_passkey'] ?? 'yes'" :note="$lang['text_download_support_passkey_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_approval_status_icon_enabled'] ?? 'Approval icon'" name="approval_status_icon_enabled" :value="$config['approval_status_icon_enabled'] ?? 'no'" :note="$lang['text_approval_status_icon_enabled_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-yesno :label="$lang['row_approval_status_none_visible'] ?? 'Approval none visible'" name="approval_status_none_visible" :value="$config['approval_status_none_visible'] ?? 'no'" :note="$lang['text_approval_status_none_visible_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-radios
        :label="$lang['row_nfo_view_style_default'] ?? 'NFO view style'"
        name="nfo_view_style_default"
        :options="collect($nfoViewStyles ?? [])->mapWithKeys(fn ($info, $style) => [(string)$style => $info['text'] ?? $style])->all()"
        :selected="(string)($config['nfo_view_style_default'] ?? 0)" />
    <x-settings-yesno :label="$lang['row_paid_torrent_enabled'] ?? 'Paid torrents'" name="paid_torrent_enabled" :value="$config['paid_torrent_enabled'] ?? 'no'" :note="$lang['text_paid_torrent_enabled_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <tr><td class="rowhead nowrap">{{ $lang['row_tax_factor'] ?? 'Tax factor' }}</td><td><input type='number' name=tax_factor style="width: 100px" value="{{ (string)($config['tax_factor'] ?? 0) }}"> {{ $lang['text_tax_factor_note'] ?? '' }}</td></tr>
    <tr><td class="rowhead nowrap">{{ $lang['row_max_price'] ?? 'Max price' }}</td><td><input type='number' name=max_price style="width: 100px" value="{{ (string)($config['max_price'] ?? 0) }}"> {{ $lang['text_max_price_note'] ?? '' }}</td></tr>
    <x-settings-text :label="$lang['row_reward_bonus_options'] ?? 'Reward options'" name="reward_bonus_options" :value="$config['reward_bonus_options'] ?? ''" :note="$lang['text_reward_bonus_options_note'] ?? ''" width="200px" />
    <tr><td class="rowhead nowrap">{{ $lang['row_reward_times_limit'] ?? 'Reward limit' }}</td><td><input type='number' name=reward_times_limit style="width: 100px" value="{{ (string)($config['reward_times_limit'] ?? 0) }}"> {{ $lang['text_reward_times_limit_note'] ?? '' }}</td></tr>
    <x-settings-row :label="$lang['row_random_promotion'] ?? 'Random promotion'">
        {{ $lang['text_random_promotion_note_one'] ?? '' }}
        <ul>
            @foreach ([
                ['randomhalfleech', 5, 'text_halfleech_chance_becoming'],
                ['randomfree', 2, 'text_free_chance_becoming'],
                ['randomtwoup', 2, 'text_twoup_chance_becoming'],
                ['randomtwoupfree', 1, 'text_freetwoup_chance_becoming'],
                ['randomtwouphalfdown', 0, 'text_twouphalfleech_chance_becoming'],
                ['randomthirtypercentdown', 0, 'text_thirtypercentleech_chance_becoming'],
            ] as [$field, $default, $noteKey])
                <li><input type="text" style="width: 50px" name="{{ $field }}" value="{{ (string)($config[$field] ?? $default) }}">{{ $lang[$noteKey] ?? '' }}</li>
            @endforeach
        </ul>
        {{ $lang['text_random_promotion_note_two'] ?? '' }}
    </x-settings-row>
    <x-settings-row :label="$lang['row_large_torrent_promotion'] ?? 'Large torrent'">
        {{ $lang['text_torrent_larger_than'] ?? '' }}<input type="text" style="width: 50px" name="largesize" value="{{ (string)($config['largesize'] ?? 20) }}">{{ $lang['text_gb_promoted_to'] ?? '' }}<select name="largepro">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Html::promotionSelection((int)($config['largepro'] ?? 2), 1)))</select>{{ $lang['text_by_system_upon_uploading'] ?? '' }}<br>{{ $lang['text_large_torrent_promotion_note'] ?? '' }}
    </x-settings-row>
    <x-settings-row :label="$lang['row_promotion_timeout'] ?? 'Promotion timeout'">
        {{ $lang['text_promotion_timeout_note_one'] ?? '' }}
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
                <li>{{ $lang[$willKey] ?? '' }}<select name="{{ $become }}">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Html::promotionSelection((int)($config[$become] ?? $defBecome), $hide)))</select>{{ $lang['text_after'] ?? ' after ' }}<input type="text" style="width: 50px" name="{{ $expire }}" value="{{ (string)($config[$expire] ?? $defExpire) }}">{{ $lang[$defKey] ?? '' }}</li>
            @endforeach
        </ul>
        {{ $lang['text_promotion_timeout_note_two'] ?? '' }}
    </x-settings-row>
    <x-settings-text :label="$lang['row_auto_pick_hot'] ?? 'Auto pick hot'" name="hotdays" :value="$config['hotdays'] ?? 7" :note="$lang['text_days_with_more_than'] ?? ''" width="50px" />
    <x-settings-text :label="$lang['row_auto_pick_hot'] ?? ''" name="hotseeder" :value="$config['hotseeder'] ?? 10" :note="$lang['text_be_picked_as_hot'] ?? ''" width="50px" />
    <x-settings-text :label="$lang['row_uploader_get_double'] ?? 'Uploader double'" name="uploaderdouble" :value="$config['uploaderdouble'] ?? 1" :note="$lang['text_times_uploading_credit'] ?? ''" width="50px" />
    <x-settings-text :label="$lang['row_delete_dead_torrents'] ?? 'Delete dead'" name="deldeadtorrent" :value="$config['deldeadtorrent'] ?? 0" :note="$lang['text_days_be_deleted'] ?? ''" width="50px" />
    <x-settings-save :label="$lang['row_save_settings'] ?? 'Save'" :text="$lang['submit_save_settings'] ?? 'Save'" />
    </form>

@elseif ($action === 'attachmentsettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_attachment">@csrf
    <x-settings-yesno :label="$lang['row_enable_attachment'] ?? 'Enable attachment'" name="enableattach" :value="$config['enableattach'] ?? 'no'" :note="$lang['text_enable_attachment_note'] ?? ''" :yes-label="$lang['text_yes'] ?? 'Yes'" :no-label="$lang['text_no'] ?? 'No'" />
    <x-settings-row :label="$lang['row_attachment_authority'] ?? 'Attachment authority'">
        <ul>
            @foreach ([
                ['one', \App\Enums\UserClass::USER, 'text_authority_default_one_one', 'text_authority_default_one_two'],
                ['two', \App\Enums\UserClass::POWER_USER, 'text_authority_default_two_one', 'text_authority_default_two_two'],
                ['three', \App\Enums\UserClass::ELITE_USER, '', ''],
                ['four', \App\Enums\UserClass::EXTREME_USER, '', ''],
            ] as [$num, $defaultClass, $defOne, $defTwo])
                <li>
                    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserClass::classSelectWithContext('class'.$num, \App\Enums\UserClass::STAFFLEADER->value, $config['class'.$num] ?? 0))){{ $lang['text_can_upload_at_most'] ?? '' }}<input type="text" style="width: 50px" name="count{{ $num }}" value="{{ (string)($config['count'.$num] ?? '') }}"> {{ $lang['text_file_size_below'] ?? '' }}<input type="text" style="width: 50px" name="size{{ $num }}" value="{{ (string)($config['size'.$num] ?? '') }}">{{ $lang['text_with_extension_name'] ?? '' }}<input type="text" style="width: 200px" name="ext{{ $num }}" value="{{ (string)($config['ext'.$num] ?? '') }}">{{ $lang[$defOne] ?? '' }}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\UserClass::name($defaultClass->value, false, true, true))){{ $lang[$defTwo] ?? '' }}
                </li>
            @endforeach
        </ul>
    </x-settings-row>
    <x-settings-text :label="$lang['row_save_directory'] ?? 'Save dir'" name="savedirectory" :value="$config['savedirectory'] ?? './attachments'" :note="$lang['text_save_directory_note'] ?? ''" width="300px" />
    <x-settings-text :label="$lang['row_http_directory'] ?? 'HTTP dir'" name="httpdirectory" :value="$config['httpdirectory'] ?? 'attachments'" :note="$lang['text_http_directory_note'] ?? ''" width="300px" />
    <x-settings-radios
        :label="$lang['row_save_directory_type'] ?? 'Dir type'"
        name="savedirectorytype"
        :options="['onedir' => $lang['text_one_directory'] ?? 'onedir', 'monthdir' => $lang['text_directories_by_monthes'] ?? 'monthdir', 'daydir' => $lang['text_directories_by_days'] ?? 'daydir']"
        :selected="$config['savedirectorytype'] ?? 'onedir'"
        :note="$lang['text_save_directory_type_note'] ?? ''"
        :break="true" />
    <x-settings-radios
        :label="$lang['row_image_thumbnails'] ?? 'Thumbnails'"
        name="thumbnailtype"
        :options="['no' => $lang['text_no_thumbnail'] ?? 'no', 'createthumb' => $lang['text_create_thumbnail'] ?? 'createthumb', 'resizebigimg' => $lang['text_resize_big_image'] ?? 'resizebigimg']"
        :selected="$config['thumbnailtype'] ?? 'no'"
        :note="$lang['text_image_thumbnail_note'] ?? ''"
        :break="true" />
    <x-settings-text :label="$lang['row_thumbnail_quality'] ?? 'Thumb quality'" name="thumbquality" :value="$config['thumbquality'] ?? 80" :note="$lang['text_thumbnail_quality_note'] ?? ''" width="100px" />
    <tr><td class="rowhead nowrap">{{ $lang['row_thumbnail_size'] ?? 'Thumb size' }}</td><td><input type='text' style="width: 100px" name="thumbwidth" value="{{ (string)($config['thumbwidth'] ?? 500) }}"> * <input type='text' style="width: 100px" name="thumbheight" value="{{ (string)($config['thumbheight'] ?? 500) }}"> {{ $lang['text_thumbnail_size_note'] ?? '' }}</td></tr>
    <tr><td class="rowhead nowrap">{{ $lang['row_alternative_thumbnail_size'] ?? 'Alt thumb size' }}</td><td><input type='text' style="width: 100px" name="altthumbwidth" value="{{ (string)($config['altthumbwidth'] ?? 180) }}"> * <input type='text' style="width: 100px" name="altthumbheight" value="{{ (string)($config['altthumbheight'] ?? 135) }}"> {{ $lang['text_alternative_thumbnail_size_note'] ?? '' }}</td></tr>
    <x-settings-save :label="$lang['row_save_settings'] ?? 'Save'" :text="$lang['submit_save_settings'] ?? 'Save'" />
    </form>

@elseif ($action === 'codesettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_code">@csrf
    <x-settings-text :label="$lang['row_main_version'] ?? 'Main version'" name="mainversion" :value="$config['mainversion'] ?? 'NexusPHP'" :note="$lang['text_main_version_note'] ?? ''" width="300px" />
    <x-settings-text :label="$lang['row_sub_version'] ?? 'Sub version'" name="subversion" :value="$config['subversion'] ?? '1.0'" :note="$lang['text_sub_version_note'] ?? ''" width="300px" />
    <x-settings-text :label="$lang['row_release_date'] ?? 'Release date'" name="releasedate" :value="$config['releasedate'] ?? '2008-12-10'" :note="$lang['text_release_date_note'] ?? ''" width="300px" />
    <x-settings-text :label="$lang['row_web_site'] ?? 'Website'" name="website" :value="$config['website'] ?? ''" :note="$lang['text_web_site_note_two'] ?? ''" width="300px" />
    <x-settings-save :label="$lang['row_save_settings'] ?? 'Save'" :text="$lang['submit_save_settings'] ?? 'Save'" />
    </form>

@elseif ($action === 'miscsettings')
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_misc">@csrf
    <tr><td class="rowhead nowrap" valign="top">{{ $lang['row_misc_donation_custom'] ?? 'Donation custom' }}</td><td><textarea cols="100" rows="10" name='donation_custom'>{{ (string)($config['donation_custom'] ?? '') }}</textarea><br>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_donation_custom_note'] ?? ''))</td></tr>
    <x-settings-text :label="$lang['row_protected_forum'] ?? 'Protected forum'" name="protected_forum" :value="$config['protected_forum'] ?? ''" :note="$lang['text_protected_forum'] ?? ''" width="100px" />
    <x-settings-save :label="$lang['row_save_settings'] ?? 'Save'" :text="$lang['submit_save_settings'] ?? 'Save'" />
    </form>

@endif

</table>
@endsection
