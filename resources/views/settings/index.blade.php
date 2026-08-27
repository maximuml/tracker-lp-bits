@php
use App\Models\User;

$lang = $lang ?? [];
$currentUser = $currentUser ?? [];
$action = $action ?? 'showmenu';
$scriptName = '/settings.php';

// Helper: yes/no radio row
$yesorno = function (string $title, string $name, mixed $value, string $note = '') use ($lang): string {
    $yes = $lang['text_yes'] ?? 'Yes';
    $no = $lang['text_no'] ?? 'No';
    $checked = $value === 'yes' ? ' checked' : '';
    $checkedNo = $value === 'no' ? ' checked' : '';
    return '<tr><td class="rowhead nowrap" valign="top" align="right">'.$title.'</td><td>'
        ."<input type='radio' id='{$name}yes' name='{$name}'{$checked} value='yes' /> <label for='{$name}yes'>{$yes}</label>"
        ." <input type='radio' id='{$name}no' name='{$name}'{$checkedNo} value='no' /> <label for='{$name}no'>{$no}</label>"
        .($note !== '' ? '<br />'.$note : '')
        .'</td></tr>';
};

// Helper: text input row
$textRow = function (string $title, string $name, mixed $value, string $note = '', string $width = '300px'): string {
    $val = htmlspecialchars((string) ($value ?? ''));
    return '<tr><td class="rowhead nowrap" valign="top" align="right">'.$title.'</td><td>'
        ."<input type='text' style=\"width: {$width}\" name='{$name}' value='{$val}'>"
        .($note !== '' ? ' '.$note : '')
        .'</td></tr>';
};

// Helper: class select
$classSelect = function (string $name, int $maxClass, mixed $selected, int $min = 0, bool $allowZero = false): string {
    return \App\Support\UserClass::classSelectWithContext($name, $maxClass, $selected, $min, $allowZero);
};
@endphp
@extends('layouts.legacy')

@section('title', $lang['head_website_settings'] ?? 'Website Settings')

@section('content')
<h1 align="center"><a class="faqlink" href="{{ $scriptName }}">{{ $lang['text_website_settings'] ?? 'Website Settings' }}</a></h1>
<table cellspacing="0" cellpadding="10" width="97%">
<tr><td colspan="2" style="padding: 10px; background: black" align="center">
<font color="white">{{ $lang['text_configuration_file_saving_note'] ?? 'Settings are stored in the database.' }}</font>
</td></tr>

@if ($action === 'showmenu')
    @php
    $sections = [
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
    ];
    @endphp
    @foreach ($sections as $act => [$row, $btn, $note])
    <tr><td class="rowhead nowrap" valign="top">{{ $lang[$row] ?? ucfirst($act) }}</td><td>
        <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="{{ $act }}">@csrf
        <input type="submit" value="{{ $lang[$btn] ?? ucfirst($act) }}"> {{ $lang[$note] ?? '' }}
        </form>
    </td></tr>
    @endforeach

@elseif ($action === 'basicsettings')
    @php $config = $config ?? []; @endphp
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_basic">@csrf
    {!! \App\Support\Html::tr($lang['row_site_name'] ?? 'Site name', "<input type='text' style=\"width: 300px\" name=SITENAME value='".htmlspecialchars((string)($config['SITENAME'] ?? 'Nexus'))."'> ".($lang['text_site_name_note'] ?? ''), 1) !!}
    {!! \App\Support\Html::tr($lang['row_base_url'] ?? 'Base URL', "<input type='text' style=\"width: 300px\" name=BASEURL value='".htmlspecialchars((string)($config['BASEURL'] ?? ''))."'> ".($lang['text_base_url_note'] ?? ''), 1) !!}
    {!! \App\Support\Html::tr($lang['row_save_settings'] ?? 'Save', "<input type='submit' name='save' value='".($lang['submit_save_settings'] ?? 'Save')."'>", 1) !!}
    </form>

@elseif ($action === 'mainsettings')
    @php $MAIN = $config ?? []; @endphp
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_main">@csrf
    {!! $yesorno($lang['row_site_online'] ?? 'Site online', 'site_online', $MAIN['site_online'] ?? 'yes', $lang['text_site_online_note'] ?? '') !!}
    {!! $yesorno($lang['row_enable_invite_system'] ?? 'Invite system', 'invitesystem', $MAIN['invitesystem'] ?? 'yes', $lang['text_invite_system_note'] ?? '') !!}
    {!! $textRow($lang['row_initial_uploading_amount'] ?? 'Initial upload', 'iniupload', $MAIN['iniupload'] ?? 0, $lang['text_initial_uploading_amount_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_initial_invites'] ?? 'Initial invites', 'invite_count', $MAIN['invite_count'] ?? 0, $lang['text_initial_invites_note'] ?? '', '50px') !!}
    {!! $textRow($lang['row_initial_tmp_invites'] ?? 'Initial tmp invites', 'tmp_invite_count', $MAIN['tmp_invite_count'] ?? 0, $lang['text_initial_tmp_invites_note'] ?? '', '50px') !!}
    {!! $textRow($lang['row_invite_timeout'] ?? 'Invite timeout', 'invite_timeout', $MAIN['invite_timeout'] ?? 0, $lang['text_invite_timeout_note'] ?? '', '50px') !!}
    {!! $yesorno($lang['row_complain_enabled'] ?? 'Complain', 'complain_enabled', $MAIN['complain_enabled'] ?? 'no', $lang['row_complain_enabled_note'] ?? '') !!}
    {!! $yesorno($lang['row_enable_registration_system'] ?? 'Registration', 'registration', $MAIN['registration'] ?? 'yes', $lang['row_allow_registrations'] ?? '') !!}
    @php
    $verificationRadio = '';
    foreach (['email' => 'text_email', 'admin' => 'text_admin', 'automatic' => 'text_automatically'] as $val => $label) {
        $checked = ($MAIN['verification'] ?? 'email') === $val ? ' checked' : '';
        $verificationRadio .= "<input type='radio' name='verification'{$checked} value='{$val}'> ".($lang[$label] ?? $val).' ';
    }
    @endphp
    {!! \App\Support\Html::tr($lang['row_verification_type'] ?? 'Verification', $verificationRadio.'<br />'.($lang['text_verification_type_note'] ?? ''), 1) !!}
    {!! $yesorno($lang['row_enable_wait_system'] ?? 'Wait system', 'waitsystem', $MAIN['waitsystem'] ?? 'no', $lang['text_wait_system_note'] ?? '') !!}
    {!! $yesorno($lang['row_enable_max_slots_system'] ?? 'Max slots', 'maxdlsystem', $MAIN['maxdlsystem'] ?? 'no', $lang['text_max_slots_system_note'] ?? '') !!}
    {!! $yesorno($lang['row_show_polls'] ?? 'Show polls', 'showpolls', $MAIN['showpolls'] ?? 'yes', $lang['text_show_polls_note'] ?? '') !!}
    {!! $yesorno($lang['row_show_stats'] ?? 'Show stats', 'showstats', $MAIN['showstats'] ?? 'yes', $lang['text_show_stats_note'] ?? '') !!}
    {!! $yesorno($lang['row_show_last_posts'] ?? 'Show last posts', 'showlastxforumposts', $MAIN['showlastxforumposts'] ?? 'yes', $lang['text_show_last_posts_note'] ?? '') !!}
    {!! $yesorno($lang['row_show_last_torrents'] ?? 'Show last torrents', 'showlastxtorrents', $MAIN['showlastxtorrents'] ?? 'yes', $lang['text_show_last_torrents_note'] ?? '') !!}
    {!! $yesorno($lang['row_show_server_load'] ?? 'Show server load', 'showtrackerload', $MAIN['showtrackerload'] ?? 'yes', $lang['text_show_server_load_note'] ?? '') !!}
    {!! $yesorno($lang['row_show_forum_stats'] ?? 'Show forum stats', 'showforumstats', $MAIN['showforumstats'] ?? 'yes', $lang['text_show_forum_stats_note'] ?? '') !!}
    {!! $yesorno($lang['row_show_top_uploader'] ?? 'Show top uploader', 'show_top_uploader', $MAIN['show_top_uploader'] ?? 'yes', $lang['text_show_top_uploader_note'] ?? '') !!}
    {!! $yesorno($lang['row_enable_nfo'] ?? 'Enable NFO', 'enablenfo', $MAIN['enablenfo'] ?? 'yes', $lang['text_enable_nfo_note'] ?? '') !!}
    {!! $yesorno($lang['row_enable_technical_info'] ?? 'Technical info', 'enable_technical_info', $MAIN['enable_technical_info'] ?? 'yes', $lang['text_enable_technical_info'] ?? '') !!}
    {!! $yesorno($lang['row_enable_global_search_system'] ?? 'Global search', 'enable_global_search', $MAIN['enable_global_search'] ?? 'no', $lang['text_global_search_system_note'] ?? '') !!}
    {!! $yesorno($lang['row_show_shoutbox'] ?? 'Show shoutbox', 'showshoutbox', $MAIN['showshoutbox'] ?? 'yes', $lang['text_show_shoutbox_note'] ?? '') !!}
    {!! $yesorno($lang['row_enable_offer_section'] ?? 'Offer section', 'showoffer', $MAIN['showoffer'] ?? 'yes', $lang['text_offer_section_note'] ?? '') !!}
    {!! $yesorno($lang['row_show_donation'] ?? 'Donation', 'donation', $MAIN['donation'] ?? 'no', $lang['text_show_donation_note'] ?? '') !!}
    {!! $yesorno($lang['row_weekend_free_uploading'] ?? 'Weekend free', 'sptime', $MAIN['sptime'] ?? 'no', $lang['text_weekend_free_uploading_note'] ?? '') !!}
    {!! $yesorno($lang['row_enable_bitbucket'] ?? 'Bitbucket', 'enablebitbucket', $MAIN['enablebitbucket'] ?? 'no', $lang['text_bitbucket_note'] ?? '') !!}
    {!! $yesorno($lang['row_ptshow_naming_style'] ?? 'PTShow naming', 'altname', $MAIN['altname'] ?? 'no', $lang['text_ptshow_naming_style_note'] ?? '') !!}
    @php
    $bcatlist = '';
    foreach (($searchboxes ?? []) as $sb) {
        $arr = (array) $sb;
        $checked = ($MAIN['browsecat'] ?? 0) == $arr['id'] ? ' checked' : '';
        $bcatlist .= "<input type=radio name=browsecat value='".$arr['id']."'{$checked}>".$arr['name'].'&nbsp;';
    }
    @endphp
    {!! \App\Support\Html::tr($lang['row_torrents_category_mode'] ?? 'Category mode', $bcatlist.'<br />'.($lang['text_torrents_category_mode_note'] ?? ''), 1) !!}
    @php
    $langlist = '';
    foreach (($allSiteLanguages ?? []) as $l) {
        $enabled = in_array($l->site_lang_folder, $allEnabledLangs ?? []);
        $langlist .= sprintf('<label><input type="checkbox" name="site_language_enabled[]" value="%s"%s/>%s</label>&nbsp;', $l->site_lang_folder, $enabled ? ' checked' : '', $l->lang_name);
    }
    @endphp
    {!! \App\Support\Html::tr($lang['row_site_language_enabled'] ?? 'Site languages', $langlist.'<br />'.($lang['text_site_language_enabled_note'] ?? ''), 1) !!}
    @php
    $deflanglist = '';
    foreach (($allSiteLanguages ?? []) as $l) {
        $enabled = in_array($l->site_lang_folder, $allEnabledLangs ?? []);
        $checked = ($MAIN['defaultlang'] ?? '') == $l->site_lang_folder ? ' checked' : '';
        $disabled = ! $enabled ? ' disabled' : '';
        $deflanglist .= sprintf('<label><input type="radio" name="defaultlang" value="%s"%s%s/>%s</label>&nbsp;', $l->site_lang_folder, $checked, $disabled, $l->lang_name);
    }
    @endphp
    {!! \App\Support\Html::tr($lang['row_default_site_language'] ?? 'Default language', $deflanglist.'<br />'.($lang['text_default_site_language_note'] ?? ''), 1) !!}
    @php
    $csslist = '<select name=defstylesheet>';
    foreach (($stylesheets ?? []) as $ss) {
        $arr = (array) $ss;
        $sel = ($MAIN['defstylesheet'] ?? 0) == $arr['id'] ? ' selected' : '';
        $csslist .= "<option value='".$arr['id']."'{$sel}>".$arr['name'].'</option>';
    }
    $csslist .= '</select>';
    @endphp
    {!! \App\Support\Html::tr($lang['row_default_stylesheet'] ?? 'Stylesheet', $csslist.'<br />'.($lang['text_default_stylesheet_note'] ?? ''), 1) !!}
    {!! $textRow($lang['row_site_logo'] ?? 'Logo', 'logo', $MAIN['logo'] ?? '', $lang['text_site_logo_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_max_torrent_size'] ?? 'Max torrent size', 'max_torrent_size', $MAIN['max_torrent_size'] ?? 1048576, $lang['text_max_torrent_size_note'] ?? '', '100px') !!}
    @php
    $announceHtml = '<ul><li>'.($lang['text_announce_default'] ?? '')."<input type='text' style=\"width: 100px\" name=announce_interval value='".htmlspecialchars((string)($MAIN['announce_interval'] ?? 1800))."'> ".($lang['text_announce_default_default'] ?? '').'</li>';
    $announceHtml .= '<li>'.($lang['text_for_torrents_older_than'] ?? '')."<input type='text' style=\"width: 100px\" name=annintertwoage value='".htmlspecialchars((string)($MAIN['annintertwoage'] ?? 7))."'>".($lang['text_days'] ?? 'days')."<input type='text' style=\"width: 100px\" name=annintertwo value='".htmlspecialchars((string)($MAIN['annintertwo'] ?? 2700))."'> ".($lang['text_announce_two_default'] ?? '').'</li>';
    $announceHtml .= '<li>'.($lang['text_for_torrents_older_than'] ?? '')."<input type='text' style=\"width: 100px\" name=anninterthreeage value='".htmlspecialchars((string)($MAIN['anninterthreeage'] ?? 30))."'>".($lang['text_days'] ?? 'days')."<input type='text' style=\"width: 100px\" name=anninterthree value='".htmlspecialchars((string)($MAIN['anninterthree'] ?? 3600))."'> ".($lang['text_announce_three_default'] ?? '').'</li></ul>';
    @endphp
    {!! \App\Support\Html::tr($lang['row_announce_interval'] ?? 'Announce interval', ($lang['text_announce_interval_note_one'] ?? '').'<br />'.$announceHtml.($lang['text_announce_interval_note_two'] ?? ''), 1) !!}
    @php
    $cleanupHtml = '<ul>';
    foreach (['one' => 'autoclean_interval_one', 'two' => 'autoclean_interval_two', 'three' => 'autoclean_interval_three', 'four' => 'autoclean_interval_four', 'five' => 'autoclean_interval_five'] as $pri => $field) {
        $cleanupHtml .= '<li>'.($lang["text_priority_{$pri}"] ?? '')."<input type='text' style=\"width: 100px\" name={$field} value='".htmlspecialchars((string)($MAIN[$field] ?? ''))."'> ".($lang["text_priority_{$pri}_note"] ?? '').'</li>';
    }
    $cleanupHtml .= '</ul>';
    @endphp
    {!! \App\Support\Html::tr($lang['row_cleanup_interval'] ?? 'Cleanup interval', ($lang['text_cleanup_interval_note_one'] ?? '').'<br />'.$cleanupHtml.($lang['text_cleanup_interval_note_two'] ?? ''), 1) !!}
    {!! $textRow($lang['row_signup_timeout'] ?? 'Signup timeout', 'signup_timeout', $MAIN['signup_timeout'] ?? 259200, $lang['text_signup_timeout_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_min_offer_votes'] ?? 'Min offer votes', 'minoffervotes', $MAIN['minoffervotes'] ?? 15, $lang['text_min_offer_votes_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_offer_vote_timeout'] ?? 'Offer vote timeout', 'offervotetimeout', $MAIN['offervotetimeout'] ?? 259200, $lang['text_offer_vote_timeout_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_offer_upload_timeout'] ?? 'Offer upload timeout', 'offeruptimeout', $MAIN['offeruptimeout'] ?? 86400, $lang['text_offer_upload_timeout_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_offer_skip_approved_count'] ?? 'Offer skip approved', 'offer_skip_approved_count', $MAIN['offer_skip_approved_count'] ?? '', $lang['text_offer_skip_approved_count_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_upload_deny_approval_deny_count'] ?? 'Upload deny count', 'upload_deny_approval_deny_count', $MAIN['upload_deny_approval_deny_count'] ?? '', $lang['text_upload_deny_approval_deny_count_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_max_subtitle_size'] ?? 'Max subtitle size', 'maxsubsize', $MAIN['maxsubsize'] ?? 3145728, $lang['text_max_subtitle_size_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_posts_per_page'] ?? 'Posts per page', 'postsperpage', $MAIN['postsperpage'] ?? 10, $lang['text_posts_per_page_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_topics_per_page'] ?? 'Topics per page', 'topicsperpage', $MAIN['topicsperpage'] ?? 20, $lang['text_topics_per_page_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_torrents_per_page'] ?? 'Torrents per page', 'torrentsperpage', $MAIN['torrentsperpage'] ?? 50, $lang['text_torrents_per_page_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_number_of_news'] ?? 'News count', 'maxnewsnum', $MAIN['maxnewsnum'] ?? 3, $lang['text_number_of_news_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_torrent_dead_time'] ?? 'Dead torrent time', 'max_dead_torrent_time', $MAIN['max_dead_torrent_time'] ?? 21600, $lang['text_torrent_dead_time_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_max_users'] ?? 'Max users', 'maxusers', $MAIN['maxusers'] ?? 2500, $lang['text_max_users'] ?? '', '100px') !!}
    {!! $textRow($lang['row_site_accountant_userid'] ?? 'Accountant ID', 'ACCOUNTANTID', $MAIN['ACCOUNTANTID'] ?? '', $lang['text_site_accountant_userid_note'] ?? '', '200px') !!}
    {!! $textRow($lang['row_alipay_account'] ?? 'Alipay', 'ALIPAYACCOUNT', $MAIN['ALIPAYACCOUNT'] ?? '', $lang['text_alipal_account_note'] ?? '', '200px') !!}
    {!! $textRow($lang['row_paypal_account'] ?? 'PayPal', 'PAYPALACCOUNT', $MAIN['PAYPALACCOUNT'] ?? '', $lang['text_paypal_account_note'] ?? '', '200px') !!}
    {!! $textRow($lang['row_site_email'] ?? 'Site email', 'SITEEMAIL', $MAIN['SITEEMAIL'] ?? '', $lang['text_site_email_note'] ?? '', '200px') !!}
    {!! $textRow($lang['row_report_email'] ?? 'Report email', 'reportemail', $MAIN['reportemail'] ?? '', $lang['text_report_email_note'] ?? '', '200px') !!}
    {!! $textRow($lang['row_site_slogan'] ?? 'Slogan', 'SLOGAN', $MAIN['SLOGAN'] ?? '', $lang['text_site_slogan_note'] ?? '', '300px') !!}
    {!! $textRow($lang['row_icp_license'] ?? 'ICP license', 'icplicense', $MAIN['icplicense'] ?? '', $lang['text_icp_license_note'] ?? '', '300px') !!}
    {!! $textRow($lang['row_torrent_directory'] ?? 'Torrent dir', 'torrent_dir', $MAIN['torrent_dir'] ?? 'torrents', $lang['text_torrent_directory'] ?? '', '100px') !!}
    {!! $textRow($lang['row_bitbucket_directory'] ?? 'Bitbucket dir', 'bitbucket', $MAIN['bitbucket'] ?? 'bitbucket', $lang['text_bitbucket_directory_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_torrent_name_prefix'] ?? 'Name prefix', 'torrentnameprefix', $MAIN['torrentnameprefix'] ?? '[Nexus]', $lang['text_torrent_name_prefix_note'] ?? '', '100px') !!}
    {!! \App\Support\Html::tr($lang['row_save_settings'] ?? 'Save', "<input type='submit' name='save' value='".($lang['submit_save_settings'] ?? 'Save')."'>", 1) !!}
    </form>

@elseif ($action === 'smtpsettings')
    @php $SMTP = $config ?? []; @endphp
    <form method="post" action="{{ $scriptName }}" name="smtpsettings_form"><input type="hidden" name="action" value="savesettings_smtp">@csrf
    {!! $yesorno($lang['row_enable_email_notification'] ?? 'Email notify', 'emailnotify', $SMTP['emailnotify'] ?? 'no', $lang['text_email_notification_note'] ?? '') !!}
    @php
    $smtpType = $SMTP['smtptype'] ?? 'default';
    $smtpRadio = '';
    foreach (['default' => 'text_smtp_default', 'advanced' => 'text_smtp_advanced', 'external' => 'text_smtp_external', 'none' => 'text_smtp_none'] as $val => $label) {
        $checked = $smtpType === $val ? ' checked' : '';
        $onclick = $val === 'advanced' ? "onclick=\"document.getElementById('smtp_advanced').style.display=''; document.getElementById('smtp_external').style.display='none';\"" : '';
        $onclick = $val === 'external' ? "onclick=\"document.getElementById('smtp_advanced').style.display='none'; document.getElementById('smtp_external').style.display='';\"" : $onclick;
        $onclick = in_array($val, ['default', 'none']) ? "onclick=\"document.getElementById('smtp_advanced').style.display='none'; document.getElementById('smtp_external').style.display='none';\"" : $onclick;
        $smtpRadio .= "<input type=\"radio\" name=\"smtptype\" value=\"{$val}\"{$onclick}{$checked}> ".($lang[$label] ?? $val).' <br />';
    }
    @endphp
    {!! \App\Support\Html::tr($lang['row_mail_function_type'] ?? 'Mail type', $smtpRadio, 1) !!}
    <tbody id="smtp_advanced" style="display: {{ $smtpType === 'advanced' ? '' : 'none' }}">
    <tr><td colspan=2 align=center><b>{{ $lang['text_setting_for_advanced_type'] ?? 'Advanced' }}</b></td></tr>
    {!! $textRow($lang['row_smtp_host'] ?? 'SMTP host', 'smtp_host', $SMTP['smtp_host'] ?? 'localhost', $lang['text_smtp_host_note'] ?? '', '300px') !!}
    {!! $textRow($lang['row_smtp_port'] ?? 'SMTP port', 'smtp_port', $SMTP['smtp_port'] ?? 25, $lang['text_smtp_port_note'] ?? '', '300px') !!}
    {!! $textRow($lang['row_smtp_sendmail_from'] ?? 'Sendmail from', 'smtp_from', $SMTP['smtp_from'] ?? '', $lang['text_smtp_sendmail_from_note'] ?? '', '300px') !!}
    </tbody>
    <tbody id="smtp_external" style="display: {{ $smtpType === 'external' ? '' : 'none' }}">
    <tr><td colspan=2 align=center><b>{{ $lang['text_setting_for_external_type'] ?? 'External' }}</b></td></tr>
    {!! $textRow($lang['row_outgoing_mail_address'] ?? 'SMTP address', 'smtpaddress', $SMTP['smtpaddress'] ?? '', $lang['text_outgoing_mail_address_note'] ?? '', '300px') !!}
    {!! $textRow($lang['row_outgoing_mail_port'] ?? 'SMTP port', 'smtpport', $SMTP['smtpport'] ?? '', $lang['text_outgoing_mail_port_note'] ?? '', '300px') !!}
    @php
    $encRadio = '';
    foreach (['' => 'none', 'tls' => 'tls', 'ssl' => 'ssl'] as $val => $label) {
        $checked = ($SMTP['encryption'] ?? '') === $val ? ' checked' : '';
        $encRadio .= "<label><input type=\"radio\" name=\"encryption\" value=\"{$val}\"{$checked}>{$label}</label>";
    }
    @endphp
    {!! \App\Support\Html::tr($lang['row_outgoing_mail_encryption'] ?? 'Encryption', $encRadio, 1) !!}
    {!! $textRow($lang['row_smtp_account_name'] ?? 'Account name', 'accountname', $SMTP['accountname'] ?? '', $lang['text_smtp_account_name_note'] ?? '', '300px') !!}
    <tr><td class="rowhead nowrap">{{ $lang['row_smtp_account_password'] ?? 'Password' }}</td><td><input type=password name=accountpassword style="width: 300px" value="{{ htmlspecialchars((string)($SMTP['accountpassword'] ?? '')) }}"> {{ $lang['text_smtp_account_password_note'] ?? '' }}</td></tr>
    </tbody>
    {!! \App\Support\Html::tr($lang['row_save_settings'] ?? 'Save', "<input type='submit' name='save' value='".($lang['submit_save_settings'] ?? 'Save')."'>", 1) !!}
    </form>

@elseif ($action === 'securitysettings')
    @php $SECURITY = $config ?? []; $AUTHORITY = $authority ?? []; @endphp
    <form method="post" action="{{ $scriptName }}" name="securitysettings_form"><input type="hidden" name="action" value="savesettings_security">@csrf
    @php
    $sslRadio = '';
    foreach (['yes' => 'text_yes', 'no' => 'text_no', 'op' => 'text_optional'] as $val => $label) {
        $checked = ($SECURITY['securelogin'] ?? 'no') === $val ? ' checked' : '';
        $sslRadio .= "<input type='radio' name='securelogin'{$checked} value='{$val}'> ".($lang[$label] ?? $val).' ';
    }
    @endphp
    {!! \App\Support\Html::tr($lang['row_enable_ssl'] ?? 'SSL', $sslRadio.'<br />'.($lang['text_ssl_note'] ?? ''), 1) !!}
    {!! $yesorno($lang['row_enable_image_verification'] ?? 'Image verification', 'iv', $SECURITY['iv'] ?? 'yes', $lang['text_image_verification_note'] ?? '') !!}
    {!! $yesorno($lang['row_allow_email_change'] ?? 'Email change', 'changeemail', $SECURITY['changeemail'] ?? 'yes', $lang['text_email_change_note'] ?? '') !!}
    @php
    $cheaterOptions = ['0' => 'select_none', '1' => 'select_conservative', '2' => 'select_normal', '3' => 'select_strict', '4' => 'select_paranoid'];
    $cheaterSelect = "<select name='cheaterdet'>";
    foreach ($cheaterOptions as $val => $label) {
        $sel = ($SECURITY['cheaterdet'] ?? 0) == $val ? ' selected' : '';
        $cheaterSelect .= "<option value={$val}{$sel}> ".($lang[$label] ?? $val).' </option>';
    }
    $cheaterSelect .= '</select> '.($lang['text_cheater_detection_level_note'] ?? '');
    @endphp
    {!! \App\Support\Html::tr($lang['row_cheater_detection_level'] ?? 'Cheater detection', $cheaterSelect.'<br />'.($lang['text_never_suspect'] ?? '').$classSelect('nodetect', $AUTHORITY['staffmem'] ?? 0, $SECURITY['nodetect'] ?? 0).($lang['text_or_above'] ?? ''), 1) !!}
    {!! $textRow($lang['row_max_ips'] ?? 'Max IPs', 'maxip', $SECURITY['maxip'] ?? 1, $lang['text_max_ips_note'] ?? '', '300px') !!}
    {!! $textRow($lang['row_max_login_attemps'] ?? 'Max login attempts', 'maxloginattempts', $SECURITY['maxloginattempts'] ?? 7, $lang['text_max_login_attemps_note'] ?? '', '300px') !!}
    {!! $yesorno($lang['row_use_challenge_response_authentication'] ?? 'Challenge response', 'use_challenge_response_authentication', $SECURITY['use_challenge_response_authentication'] ?? 'no', $lang['text_use_challenge_response_authentication_note'] ?? '') !!}
    @php
    $guestTypes = ['normal' => 'text_guest_visit_type_normal', 'static_page' => 'text_guest_visit_type_static_page', 'custom_content' => 'text_guest_visit_type_custom_content', 'redirect' => 'text_guest_visit_type_redirect'];
    $guestRadio = '';
    foreach ($guestTypes as $val => $label) {
        $checked = ($SECURITY['guest_visit_type'] ?? 'normal') === $val ? ' checked' : '';
        $guestRadio .= "<label><input type=\"radio\" name=\"guest_visit_type\" value=\"{$val}\"{$checked}>" . ($lang[$label] ?? $val) . '</label><br/>';
    }
    @endphp
    {!! \App\Support\Html::tr($lang['row_guest_visit_type'] ?? 'Guest visit', $guestRadio, 1) !!}
    <tbody id="tbody_static_page" style="display: {{ ($SECURITY['guest_visit_type'] ?? '') === 'static_page' ? 'table-row-group' : 'none' }}">
    @php
    $staticSelect = '<select name="guest_visit_value_static_page">';
    foreach (($staticPages ?? []) as $page) {
        $sel = ($SECURITY['guest_visit_value_static_page'] ?? '') === $page ? ' selected' : '';
        $staticSelect .= sprintf('<option value="%s"%s>%s</option>', $page, $sel, $page);
    }
    $staticSelect .= '</select> '.($lang['text_guest_visit_value_static_page'] ?? '');
    @endphp
    {!! \App\Support\Html::tr($lang['row_guest_visit_value_static_page'] ?? 'Static page', $staticSelect, 1) !!}
    </tbody>
    <tbody id="tbody_custom_content" style="display: {{ ($SECURITY['guest_visit_type'] ?? '') === 'custom_content' ? 'table-row-group' : 'none' }}">
    <tr><td class="rowhead nowrap" valign="top">{{ $lang['row_guest_visit_value_custom_content'] ?? 'Custom content' }}</td><td>{!! \App\Support\Form::bbcodeEditor('securitysettings_form', 'guest_visit_value_custom_content', $SECURITY['guest_visit_value_custom_content'] ?? '') !!}</td></tr>
    </tbody>
    <tbody id="tbody_redirect" style="display: {{ ($SECURITY['guest_visit_type'] ?? '') === 'redirect' ? 'table-row-group' : 'none' }}">
    {!! $textRow($lang['row_guest_visit_value_redirect'] ?? 'Redirect URL', 'guest_visit_value_redirect', $SECURITY['guest_visit_value_redirect'] ?? '', '', '300px') !!}
    </tbody>
    @php
    $loginTypes = ['normal' => 'text_login_type_normal', 'secret' => 'text_login_type_secret', 'passkey' => 'text_login_type_passkey'];
    $loginRadio = '';
    foreach ($loginTypes as $val => $label) {
        $checked = ($SECURITY['login_type'] ?? 'normal') === $val ? ' checked' : '';
        $loginRadio .= "<label><input type=\"radio\" name=\"login_type\" value=\"{$val}\"{$checked}>" . ($lang[$label] ?? $val) . '</label>';
    }
    $loginRadio .= sprintf('<b style="color: #DC143C; margin-left: 20px">%s</b>', $lang['text_login_type_warning'] ?? '');
    @endphp
    {!! \App\Support\Html::tr($lang['row_login_type'] ?? 'Login type', $loginRadio, 1) !!}
    <tbody id="tbody_login_secret" style="display: {{ in_array($SECURITY['login_type'] ?? '', ['secret', 'passkey']) ? 'table-row-group' : 'none' }}">
    @php
    $loginSecret = sprintf('%s：%s', $lang['text_login_secret_current'] ?? 'Current secret', $SECURITY['login_secret'] ?? '');
    if (! empty($SECURITY['login_secret'])) {
        $loginSecret .= sprintf('<br/>%s: %s/login.php?secret=%s', $lang['text_login_url_with_secret'] ?? '', \App\Support\Url::schemeAndHost(false), $SECURITY['login_secret']);
    }
    $loginSecret .= sprintf('<br/><label><input type="radio" name="login_secret_regenerate" value="no"%s />%s</label>', !empty($SECURITY['login_secret']) ? ' checked' : '', $lang['text_login_secret_regenerate_no'] ?? 'No');
    $loginSecret .= sprintf('<br/><label><input type="radio" name="login_secret_regenerate" value="yes"%s />%s</label>', empty($SECURITY['login_secret']) ? ' checked' : '', $lang['text_login_secret_regenerate_yes'] ?? 'Yes');
    @endphp
    {!! \App\Support\Html::tr($lang['row_login_secret'] ?? 'Login secret', $loginSecret, 1) !!}
    <tr><td class="rowhead nowrap">{{ $lang['row_login_secret_lifetime'] ?? 'Secret lifetime' }}</td><td><input type="text" name="login_secret_lifetime" value="{{ htmlspecialchars((string)($SECURITY['login_secret_lifetime'] ?? '')) }}">{{ $lang['text_login_secret_lifetime_unit'] ?? ' min' }}</td></tr>
    </tbody>
    {!! \App\Support\Html::tr($lang['row_save_settings'] ?? 'Save', "<input type='submit' name='save' value='".($lang['submit_save_settings'] ?? 'Save')."'>", 1) !!}
    </form>

@elseif ($action === 'authoritysettings')
    @php $AUTHORITY = $config ?? []; $maxclass = User::CLASS_SYSOP; @endphp
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_authority">@csrf
    @php
    $permRows = [
        ['defaultclass', 'row_default_class', 'text_default_user_class', 'text_default', User::CLASS_USER, 'text_default_class_note'],
        ['staffmem', 'row_staff_member', 'text_minimum_class', 'text_default', User::CLASS_MODERATOR, 'text_staff_member_note'],
        ['newsmanage', 'row_news_management', 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, 'text_news_management_note'],
        ['sbmanage', 'row_shoutbox_management', 'text_minimum_class', 'text_default', User::CLASS_MODERATOR, 'text_shoutbox_management_note'],
        ['pollmanage', 'row_poll_management', 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, 'text_poll_management_note'],
        ['postmanage', 'row_forum_post_management', 'text_minimum_class', 'text_default', User::CLASS_MODERATOR, 'text_forum_post_management_note'],
        ['commanage', 'row_comment_management', 'text_minimum_class', 'text_default', User::CLASS_MODERATOR, 'text_comment_management_note'],
        ['forummanage', 'row_forum_management', 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, 'text_forum_management_note'],
        ['viewuserlist', 'row_view_userlist', 'text_minimum_class', 'text_default', User::CLASS_POWER_USER, 'text_view_userlist_note'],
        ['torrentmanage', 'row_torrent_management', 'text_minimum_class', 'text_default', User::CLASS_MODERATOR, 'text_torrent_management_note'],
        ['torrent-delete', 'row_torrent_delete', 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, 'text_torrent_delete_note'],
        ['torrentsticky', 'row_torrent_sticky', 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, 'text_torrent_sticky_note'],
        ['torrenton_promotion', 'row_torrent_on_promotion', 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, 'text_torrent_promotion_note'],
        ['torrent_hr', 'row_torrent_hr', 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, 'text_torrent_hr_note'],
        ['torrent-set-special-tag', null, 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, null],
        ['torrent-approval', null, 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, null],
        ['torrent-approval-allow-automatic', null, 'text_minimum_class', 'text_default', User::CLASS_UPLOADER, null],
        ['torrent-set-price', null, 'text_minimum_class', 'text_default', User::CLASS_UPLOADER, null],
        ['askreseed', 'row_ask_for_reseed', 'text_minimum_class', 'text_default', User::CLASS_POWER_USER, 'text_ask_for_reseed_note'],
        ['viewnfo', 'row_view_nfo', 'text_minimum_class', 'text_default', User::CLASS_POWER_USER, 'text_view_nfo_note'],
        ['torrentstructure', 'row_view_torrent_structure', 'text_minimum_class', 'text_default', User::CLASS_ULTIMATE_USER, 'text_view_torrent_structure_note'],
        ['sendinvite', 'row_send_invite', 'text_minimum_class', 'text_default', User::CLASS_POWER_USER, 'text_send_invite_note'],
        ['viewhistory', 'row_view_history', 'text_minimum_class', 'text_default', User::CLASS_VETERAN_USER, 'text_view_history_note'],
        ['topten', 'row_view_topten', 'text_minimum_class', 'text_default', User::CLASS_POWER_USER, 'text_view_topten_note'],
        ['log', 'row_view_general_log', 'text_minimum_class', 'text_default', User::CLASS_INSANE_USER, 'text_view_general_log_note'],
        ['confilog', 'row_view_confidential_log', 'text_minimum_class', 'text_default', User::CLASS_MODERATOR, 'text_view_confidential_log_note'],
        ['userprofile', 'row_view_user_confidential', 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, 'text_view_user_confidential_note'],
        ['torrenthistory', 'row_view_user_torrent', 'text_minimum_class', 'text_default', User::CLASS_POWER_USER, 'text_view_user_torrent_note'],
        ['prfmanage', 'row_general_profile_management', 'text_minimum_class', 'text_default', User::CLASS_MODERATOR, 'text_general_profile_management_note'],
        ['cruprfmanage', 'row_crucial_profile_management', 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, 'text_crucial_profile_management_note'],
        ['uploadsub', 'row_upload_subtitle', 'text_minimum_class', 'text_default', User::CLASS_USER, 'text_upload_subtitle_note'],
        ['delownsub', 'row_delete_own_subtitle', 'text_minimum_class', 'text_default', User::CLASS_POWER_USER, 'text_delete_own_subtitle_note'],
        ['submanage', 'row_subtitle_management', 'text_minimum_class', 'text_default', User::CLASS_MODERATOR, 'text_subtitle_management'],
        ['updateextinfo', 'row_update_external_info', 'text_minimum_class', 'text_default', User::CLASS_EXTREME_USER, 'text_update_external_info_note'],
        ['viewanonymous', 'row_view_anonymous', 'text_minimum_class', 'text_default', User::CLASS_UPLOADER, 'text_view_anonymous_note'],
        ['beanonymous', 'row_be_anonymous', 'text_minimum_class', 'text_default', User::CLASS_CRAZY_USER, 'text_be_anonymous_note'],
        ['addoffer', 'row_add_offer', 'text_minimum_class', 'text_default', User::CLASS_PEASANT, 'text_add_offer_note'],
        ['offermanage', 'row_offer_management', 'text_minimum_class', 'text_default', User::CLASS_MODERATOR, 'text_offer_management_note'],
        ['upload', 'row_upload_torrent', 'text_minimum_class', 'text_default', User::CLASS_POWER_USER, 'text_upload_torrent_note'],
        ['movetorrent', 'row_move_torrent', 'text_minimum_class', 'text_default', User::CLASS_MODERATOR, 'text_move_torrent_note'],
        ['chrmanage', 'row_chronicle_management', 'text_minimum_class', 'text_default', User::CLASS_MODERATOR, 'text_chronicle_management_note'],
        ['viewinvite', 'row_view_invite', 'text_minimum_class', 'text_default', User::CLASS_MODERATOR, 'text_view_invite_note'],
        ['buyinvite', 'row_buy_invites', 'text_minimum_class', 'text_default', User::CLASS_INSANE_USER, 'text_buy_invites_note'],
        ['seebanned', 'row_see_banned_torrents', 'text_minimum_class', 'text_default', User::CLASS_UPLOADER, 'text_see_banned_torrents_note'],
        ['againstoffer', 'row_vote_against_offers', 'text_minimum_class', 'text_default', User::CLASS_USER, 'text_vote_against_offers_note'],
        ['userbar', 'row_allow_userbar', 'text_minimum_class', 'text_default', User::CLASS_POWER_USER, 'text_allow_userbar_note'],
        ['user-delete', null, 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, null],
        ['user-change-class', null, 'text_minimum_class', 'text_default', User::CLASS_ADMINISTRATOR, null],
    ];
    @endphp
    @foreach ($permRows as [$perm, $rowKey, $minKey, $defKey, $defaultClass, $noteKey])
    @php
    $title = $rowKey !== null ? ($lang[$rowKey] ?? ucfirst($perm)) : \App\Support\Locale::trans("permission.{$perm}.text", [], null);
    $note = $noteKey !== null ? ($lang[$noteKey] ?? '') : \App\Support\Locale::trans("permission.{$perm}.desc", [], null);
    $content = ($lang[$minKey] ?? 'Min class: ').$classSelect($perm, $maxclass, $AUTHORITY[$perm] ?? 0, 0, true).($lang[$defKey] ?? ' Default: ').\App\Support\UserClass::name($defaultClass, false, true, true).$note;
    @endphp
    {!! \App\Support\Html::tr($title, $content, 1) !!}
    @endforeach
    {!! \App\Support\Html::tr($lang['row_save_settings'] ?? 'Save', "<input type='submit' name='save' value='".($lang['submit_save_settings'] ?? 'Save')."'>", 1) !!}
    </form>

@elseif ($action === 'tweaksettings')
    @php $TWEAK = $config ?? []; @endphp
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_tweak">@csrf
    {!! $yesorno($lang['row_save_user_location'] ?? 'Save location', 'where', $TWEAK['where'] ?? 'no', $lang['text_save_user_location_note'] ?? '') !!}
    @php
    $bonusRadio = '';
    foreach (['enable' => 'text_enabled', 'disablesave' => 'text_disabled_but_save', 'disable' => 'text_disabled_no_save'] as $val => $label) {
        $checked = ($TWEAK['bonus'] ?? 'enable') === $val ? " checked='checked'" : '';
        $bonusRadio .= "<input type='radio' id='bonus{$val}' name='bonus'{$checked} value='{$val}'> <label for='bonus{$val}'>".($lang[$label] ?? $val).'</label> ';
    }
    @endphp
    {!! \App\Support\Html::tr($lang['row_kps_enabled'] ?? 'Bonus enabled', $bonusRadio.'<br />'.($lang['text_kps_note'] ?? ''), 1) !!}
    {!! $yesorno($lang['row_enable_location'] ?? 'Enable location', 'enablelocation', $TWEAK['enablelocation'] ?? 'no', $lang['text_enable_location_note'] ?? '') !!}
    {!! $yesorno($lang['row_enable_tooltip'] ?? 'Enable tooltip', 'enabletooltip', $TWEAK['enabletooltip'] ?? 'no', $lang['text_enable_tooltip_note'] ?? '') !!}
    {!! $textRow($lang['row_title_keywords'] ?? 'Title keywords', 'titlekeywords', $TWEAK['titlekeywords'] ?? '', $lang['text_title_keywords_note'] ?? '', '300px') !!}
    {!! $textRow($lang['row_meta_keywords'] ?? 'Meta keywords', 'metakeywords', $TWEAK['metakeywords'] ?? '', $lang['text_meta_keywords_note'] ?? '', '300px') !!}
    <tr><td class="rowhead nowrap" valign="top">{{ $lang['row_meta_description'] ?? 'Meta description' }}</td><td><textarea cols="100" style="width: 450px;" rows="5" name='metadescription'>{{ htmlspecialchars((string)($TWEAK['metadescription'] ?? '')) }}</textarea><br />{{ $lang['text_meta_description_note'] ?? '' }}</td></tr>
    <tr><td class="rowhead nowrap" valign="top">{{ $lang['row_web_analytics_code'] ?? 'Analytics code' }}</td><td><textarea cols="100" style="width: 450px;" rows="5" name='analyticscode'>{{ htmlspecialchars((string)($TWEAK['analyticscode'] ?? '')) }}</textarea><br />{{ $lang['text_web_analytics_code_note'] ?? '' }}</td></tr>
    <tr><td class="rowhead nowrap">{{ $lang['row_see_sql_debug'] ?? 'SQL debug' }}</td><td><input type='checkbox' name='enablesqldebug' value='yes'{{ ($TWEAK['enablesqldebug'] ?? 'no') === 'yes' ? " checked='checked'" : '' }}>{{ $lang['text_allow'] ?? 'Allow' }}{!! $classSelect('sqldebug', User::CLASS_STAFF_LEADER, $TWEAK['sqldebug'] ?? User::CLASS_MODERATOR) !!}{{ $lang['text_see_sql_list'] ?? '' }}{!! \App\Support\UserClass::name(User::CLASS_SYSOP, false, true, true) !!}</td></tr>
    {!! $textRow($lang['row_tracker_founded_date'] ?? 'Founded date', 'datefounded', $TWEAK['datefounded'] ?? '2007-12-24', $lang['text_tracker_founded_date_note'] ?? '', '300px') !!}
    {!! $textRow($lang['row_css_date'] ?? 'CSS date', 'cssdate', $TWEAK['cssdate'] ?? '', $lang['text_css_date'] ?? '', '300px') !!}
    {!! \App\Support\Html::tr($lang['row_save_settings'] ?? 'Save', "<input type='submit' name='save' value='".($lang['submit_save_settings'] ?? 'Save')."'>", 1) !!}
    </form>

@elseif ($action === 'bonussettings')
    @php $BONUS = $config ?? []; @endphp
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_bonus">@csrf
    <tr><td colspan=2 align=center><b>{{ $lang['text_bonus_by_seeding'] ?? 'Bonus by seeding' }}</b></td></tr>
    {!! $textRow($lang['row_min_size'] ?? 'Min size', 'min_size', $BONUS['min_size'] ?? 0, $lang['text_bonus_mini_size'] ?? ''.$lang['text_bonus_mini_size_help'] ?? '', '100px') !!}
    {!! $textRow($lang['row_donor_gets_double'] ?? 'Donor double', 'donortimes', $BONUS['donortimes'] ?? 2, $lang['text_donor_gets'] ?? ''.$lang['text_times_as_many'] ?? '', '50px') !!}
    @php
    $seedingHtml = ($lang['text_user_would_get'] ?? '')."<input type='text' style=\"width: 50px\" name=perseeding value='".htmlspecialchars((string)($BONUS['perseeding'] ?? 1))."'>".($lang['text_bonus_points'] ?? '')."<input type='text' style=\"width: 50px\" name=maxseeding value='".htmlspecialchars((string)($BONUS['maxseeding'] ?? 7))."'>".($lang['text_torrents_default'] ?? '');
    @endphp
    {!! \App\Support\Html::tr($lang['row_basic_seeding_bonus'] ?? 'Basic seeding', $seedingHtml, 1) !!}
    <tr><td colspan=2 align=center><b>{{ $lang['text_misc_ways_get_bonus'] ?? 'Misc bonus' }}</b></td></tr>
    @php
    $miscBonus = [
        ['uploadtorrent', 'row_uploading_torrent', 15, 'text_uploading_torrent_note'],
        ['starttopic', 'row_starting_topic', 2, 'text_starting_topic_note'],
        ['makepost', 'row_making_post', 1, 'text_making_post_note'],
        ['addcomment', 'row_adding_comment', 1, 'text_adding_comment_note'],
        ['pollvote', 'row_voting_on_poll', 1, 'text_voting_on_poll_note'],
        ['offervote', 'row_voting_on_offer', 1, 'text_voting_on_offer_note'],
    ];
    @endphp
    @foreach ($miscBonus as [$field, $rowKey, $default, $noteKey])
    {!! \App\Support\Html::tr($lang[$rowKey] ?? ucfirst($field), ($lang['text_user_would_get'] ?? '')."<input type='text' style=\"width: 50px\" name={$field} value='".htmlspecialchars((string)($BONUS[$field] ?? $default))."'>".($lang[$noteKey] ?? ''), 1) !!}
    @endforeach
    {!! \App\Support\Html::tr($lang['row_saying_thanks'] ?? 'Thanks', ($lang['text_giver_and_receiver_get'] ?? '')."<input type='text' style=\"width: 50px\" name=saythanks value='".htmlspecialchars((string)($BONUS['saythanks'] ?? 0.5))."'>".($lang['text_saying_thanks_and'] ?? '')."<input type='text' style=\"width: 50px\" name=receivethanks value='".htmlspecialchars((string)($BONUS['receivethanks'] ?? 0))."'>".($lang['text_saying_thanks_default'] ?? ''), 1) !!}
    <tr><td colspan=2 align=center><b>{{ $lang['text_things_cost_bonus'] ?? 'Things that cost bonus' }}</b></td></tr>
    @php
    $costItems = [
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
    ];
    @endphp
    @foreach ($costItems as [$field, $rowKey, $default, $noteKey])
    {!! \App\Support\Html::tr($lang[$rowKey] ?? ucfirst($field), ($lang['text_it_costs_user'] ?? '')."<input type='text' style=\"width: 50px\" name={$field} value='".htmlspecialchars((string)($BONUS[$field] ?? $default))."'>".($lang[$noteKey] ?? ''), 1) !!}
    @endforeach
    {!! $yesorno($lang['row_allow_giving_bonus_gift'] ?? 'Bonus gift', 'bonusgift', $BONUS['bonusgift'] ?? 'no', $lang['text_giving_bonus_gift_note'] ?? '') !!}
    {!! \App\Support\Html::tr($lang['row_bonus_gift_tax'] ?? 'Gift tax', ($lang['text_system_charges'] ?? '')."<input type='text' style=\"width: 50px\" name='basictax' value='".htmlspecialchars((string)($BONUS['basictax'] ?? 5))."'>".($lang['text_bonus_points_plus'] ?? '')."<input type='text' style=\"width: 50px\" name='taxpercentage' value='".htmlspecialchars((string)($BONUS['taxpercentage'] ?? 10))."'>".($lang['text_bonus_gift_tax_note'] ?? ''), 1) !!}
    <tr><td colspan="2" align="center"><b>{{ $lang['text_attendance_get_bonus'] ?? 'Attendance bonus' }}</b></td></tr>
    {!! $textRow($lang['text_attendance_initial_reward'] ?? 'Initial reward', 'attendance_initial', $BONUS['attendance_initial'] ?? 0, '', '30px') !!}
    {!! $textRow($lang['text_attendance_continuous_increment'] ?? 'Step', 'attendance_step', $BONUS['attendance_step'] ?? 0, '', '30px') !!}
    {!! $textRow($lang['text_attendance_reward_limit'] ?? 'Max', 'attendance_max', $BONUS['attendance_max'] ?? 0, '', '50px') !!}
    @php
    $continuousRows = '';
    foreach (($attendance_continuous ?? []) as $days => $value) {
        $continuousRows .= sprintf('<tr><td><input type="number" min="0" style="width: 40px" name="attendance_continuous_day[]" value="%u" /> %s</td><td><input type="number" min="0" style="width: 50px;" name="attendance_continuous_value[]" value="%u" /> %s</td><td><a href="javascript:;" onclick="DelRow(this);">%s</a></td></tr>', $days, $lang['text_attendance_continuous_unit'] ?? 'days', $value, $lang['text_attendance_input_suffix'] ?? '', $lang['text_attendance_continuous_item_action_remove'] ?? 'Remove');
    }
    $continuousRows .= '<tr><td colspan="3">'.($lang['text_attendance_continuous_add_rules'] ?? '').'</td></tr><tr><td><input type="number" min="0" style="width: 40px" name="attendance_continuous_day[]" value="" /> '.($lang['text_attendance_continuous_unit'] ?? 'days').'</td><td><input type="number" min="0" style="width: 50px;" name="attendance_continuous_value[]" value="" /> '.($lang['text_attendance_input_suffix'] ?? '').'</td><td><a href="javascript:;" onclick="NewRow(this,false);">'.($lang['text_attendance_continuous_item_action_add'] ?? 'Add').'</a></td></tr>';
    @endphp
    {!! \App\Support\Html::tr($lang['text_attendance_continuous'] ?? 'Continuous', '<table><tr><td class="colhead">'.($lang['text_attendance_continuous_days'] ?? 'Days').'</td><td class="colhead">'.($lang['text_attendance_continuous_days_additional_reward'] ?? 'Reward').'</td><td class="colhead">'.($lang['text_attendance_continuous_days_action'] ?? 'Action').'</td></tr>'.$continuousRows.'</table>', true) !!}
    {!! \App\Support\Html::tr($lang['row_save_settings'] ?? 'Save', "<input type='submit' name='save' value='".($lang['submit_save_settings'] ?? 'Save')."'>", 1) !!}
    </form>

@elseif ($action === 'accountsettings')
    @php $ACCOUNT = $config ?? []; $maxclass = User::CLASS_VIP; @endphp
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_account">@csrf
    <tr><td colspan=2 align=center><b>{{ $lang['text_delete_inactive_accounts'] ?? 'Delete inactive' }}</b></td></tr>
    {!! \App\Support\Html::tr($lang['row_never_delete'] ?? 'Never delete', $classSelect('neverdelete', $maxclass, $ACCOUNT['neverdelete'] ?? 0).($lang['text_never_delete'] ?? '').\App\Support\UserClass::name(User::CLASS_VETERAN_USER, false, true, true), 1) !!}
    {!! \App\Support\Html::tr($lang['row_never_delete_if_packed'] ?? 'Never delete packed', $classSelect('neverdeletepacked', $maxclass, $ACCOUNT['neverdeletepacked'] ?? 0).($lang['text_never_delete_if_packed'] ?? '').\App\Support\UserClass::name(User::CLASS_ELITE_USER, false, true, true), 1) !!}
    {!! $textRow($lang['row_delete_packed'] ?? 'Delete packed', 'deletepacked', $ACCOUNT['deletepacked'] ?? 400, $lang['text_delete_packed_note_two'] ?? '', '50px') !!}
    {!! $textRow($lang['row_delete_unpacked'] ?? 'Delete unpacked', 'deleteunpacked', $ACCOUNT['deleteunpacked'] ?? 150, $lang['text_delete_unpacked_note_two'] ?? '', '50px') !!}
    {!! $textRow($lang['row_delete_no_transfer'] ?? 'Delete no transfer', 'deletenotransfer', $ACCOUNT['deletenotransfer'] ?? 60, $lang['text_delete_transfer_note_two'] ?? '', '50px') !!}
    {!! $textRow($lang['row_destroy_disabled'] ?? 'Destroy disabled', 'destroy_disabled', $ACCOUNT['destroy_disabled'] ?? 500, $lang['text_destroy_disabled_note_two'] ?? '', '50px') !!}
    <tr><td colspan=2 align=center><b>{{ $lang['text_user_promotion_demotion'] ?? 'Promotion/Demotion' }}</b></td></tr>
    @php
    $promotions = [
        [User::CLASS_POWER_USER, 'pu', 4, 50, 1.05, 0.95, 1],
        [User::CLASS_ELITE_USER, 'eu', 8, 120, 1.55, 1.45, 0],
        [User::CLASS_CRAZY_USER, 'cu', 15, 300, 2.05, 1.95, 2],
        [User::CLASS_INSANE_USER, 'iu', 25, 500, 2.55, 2.45, 0],
        [User::CLASS_VETERAN_USER, 'vu', 40, 750, 3.05, 2.95, 3],
        [User::CLASS_EXTREME_USER, 'exu', 60, 1024, 3.55, 3.45, 0],
        [User::CLASS_ULTIMATE_USER, 'uu', 80, 1536, 4.05, 3.95, 5],
        [User::CLASS_NEXUS_MASTER, 'nm', 100, 3072, 4.55, 4.45, 10],
    ];
    @endphp
    @foreach ($promotions as [$class, $prefix, $time, $dl, $prratio, $deratio, $invites])
    @php
    $aliasField = $class.'_alias';
    $timeField = $prefix.'time';
    $dlField = $prefix.'dl';
    $prratioField = $prefix.'prratio';
    $deratioField = $prefix.'deratio';
    $seedPointsField = $class.'_min_seed_points';
    $title = ($lang['row_promote_to_one'] ?? 'Promote to ').\App\Support\UserClass::name($class, false, false, true).($lang['row_promote_to_two'] ?? '');
    $html = ($lang['text_alias'] ?? 'Alias: ')."<input type='text' style=\"width: 60px\" name='".$aliasField."' value='".htmlspecialchars((string)($ACCOUNT[$aliasField] ?? ''))."'><br/>"
        .($lang['text_member_longer_than'] ?? 'Member for ')."<input type='text' style=\"width: 50px\" name='".$timeField."' value='".htmlspecialchars((string)($ACCOUNT[$timeField] ?? $time))."'>"
        .($lang['text_seed_points_more_than'] ?? ' Seed points: ')."<input type='text' style=\"width: 60px\" name='".$seedPointsField."' value='".htmlspecialchars((string)($ACCOUNT[$seedPointsField] ?? 0))."'>"
        .($lang['text_downloaded_more_than'] ?? ' Downloaded: ')."<input type='text' style=\"width: 50px\" name='".$dlField."' value='".htmlspecialchars((string)($ACCOUNT[$dlField] ?? $dl))."'>"
        .($lang['text_with_ratio_above'] ?? ' Ratio: ')."<input type='text' style=\"width: 50px\" name='".$prratioField."' value='".htmlspecialchars((string)($ACCOUNT[$prratioField] ?? $prratio))."'>"
        .($lang['text_demote_with_ratio_below'] ?? ' Demote below: ')."<input type='text' style=\"width: 50px\" name='".$deratioField."' value='".htmlspecialchars((string)($ACCOUNT[$deratioField] ?? $deratio))."'>"
        .($lang['text_users_get'] ?? ' Invites: ')."<input type='text' style=\"width: 50px\" name='getInvitesByPromotion[".$class."]' value='".htmlspecialchars((string)($ACCOUNT['getInvitesByPromotion'][$class] ?? $invites))."'>";
    @endphp
    {!! \App\Support\Html::tr($title, $html, 1) !!}
    @endforeach
    {!! \App\Support\Html::tr($lang['row_save_settings'] ?? 'Save', "<input type='submit' name='save' value='".($lang['submit_save_settings'] ?? 'Save')."'>", 1) !!}
    </form>

@elseif ($action === 'torrentsettings')
    @php $TORRENT = $config ?? []; @endphp
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_torrent">@csrf
    {!! $textRow($lang['row_sticky_first_level_background_color'] ?? 'Sticky 1st color', 'sticky_first_level_background_color', $TORRENT['sticky_first_level_background_color'] ?? '', $lang['text_sticky_first_level_background_color_note'] ?? '', '100px') !!}
    {!! $textRow($lang['row_sticky_second_level_background_color'] ?? 'Sticky 2nd color', 'sticky_second_level_background_color', $TORRENT['sticky_second_level_background_color'] ?? '', $lang['text_sticky_second_level_background_color_note'] ?? '', '100px') !!}
    {!! $yesorno($lang['row_download_support_passkey'] ?? 'Passkey download', 'download_support_passkey', $TORRENT['download_support_passkey'] ?? 'yes', $lang['text_download_support_passkey_note'] ?? '') !!}
    {!! $yesorno($lang['row_approval_status_icon_enabled'] ?? 'Approval icon', 'approval_status_icon_enabled', $TORRENT['approval_status_icon_enabled'] ?? 'no', $lang['text_approval_status_icon_enabled_note'] ?? '') !!}
    {!! $yesorno($lang['row_approval_status_none_visible'] ?? 'Approval none visible', 'approval_status_none_visible', $TORRENT['approval_status_none_visible'] ?? 'no', $lang['text_approval_status_none_visible_note'] ?? '') !!}
    @php
    $nfoRadio = '';
    foreach (($nfoViewStyles ?? []) as $style => $info) {
        $checked = ($TORRENT['nfo_view_style_default'] ?? 0) == $style ? ' checked' : '';
        $nfoRadio .= sprintf('<label><input type="radio" name="nfo_view_style_default" value="%s"%s>%s</label>', $style, $checked, $info['text'] ?? $style);
    }
    @endphp
    {!! \App\Support\Html::tr($lang['row_nfo_view_style_default'] ?? 'NFO view style', $nfoRadio, 1) !!}
    {!! $yesorno($lang['row_paid_torrent_enabled'] ?? 'Paid torrents', 'paid_torrent_enabled', $TORRENT['paid_torrent_enabled'] ?? 'no', $lang['text_paid_torrent_enabled_note'] ?? '') !!}
    <tr><td class="rowhead nowrap">{{ $lang['row_tax_factor'] ?? 'Tax factor' }}</td><td><input type='number' name=tax_factor style="width: 100px" value="{{ htmlspecialchars((string)($TORRENT['tax_factor'] ?? 0)) }}"> {{ $lang['text_tax_factor_note'] ?? '' }}</td></tr>
    <tr><td class="rowhead nowrap">{{ $lang['row_max_price'] ?? 'Max price' }}</td><td><input type='number' name=max_price style="width: 100px" value="{{ htmlspecialchars((string)($TORRENT['max_price'] ?? 0)) }}"> {{ $lang['text_max_price_note'] ?? '' }}</td></tr>
    {!! $textRow($lang['row_reward_bonus_options'] ?? 'Reward options', 'reward_bonus_options', $TORRENT['reward_bonus_options'] ?? '', $lang['text_reward_bonus_options_note'] ?? '', '200px') !!}
    <tr><td class="rowhead nowrap">{{ $lang['row_reward_times_limit'] ?? 'Reward limit' }}</td><td><input type='number' name=reward_times_limit style="width: 100px" value="{{ htmlspecialchars((string)($TORRENT['reward_times_limit'] ?? 0)) }}"> {{ $lang['text_reward_times_limit_note'] ?? '' }}</td></tr>
    @php
    $randomFields = [
        ['randomhalfleech', 5, 'text_halfleech_chance_becoming'],
        ['randomfree', 2, 'text_free_chance_becoming'],
        ['randomtwoup', 2, 'text_twoup_chance_becoming'],
        ['randomtwoupfree', 1, 'text_freetwoup_chance_becoming'],
        ['randomtwouphalfdown', 0, 'text_twouphalfleech_chance_becoming'],
        ['randomthirtypercentdown', 0, 'text_thirtypercentleech_chance_becoming'],
    ];
    $randomHtml = ($lang['text_random_promotion_note_one'] ?? '').'<ul>';
    foreach ($randomFields as [$field, $default, $noteKey]) {
        $randomHtml .= "<li><input type='text' style=\"width: 50px\" name={$field} value='".htmlspecialchars((string)($TORRENT[$field] ?? $default))."'>".($lang[$noteKey] ?? '').'</li>';
    }
    $randomHtml .= '</ul>'.($lang['text_random_promotion_note_two'] ?? '');
    @endphp
    {!! \App\Support\Html::tr($lang['row_random_promotion'] ?? 'Random promotion', $randomHtml, 1) !!}
    @php
    $largeHtml = ($lang['text_torrent_larger_than'] ?? '')."<input type='text' style=\"width: 50px\" name=largesize value='".htmlspecialchars((string)($TORRENT['largesize'] ?? 20))."'>".($lang['text_gb_promoted_to'] ?? '')."<select name=largepro>".\App\Support\Html::promotionSelection((int)($TORRENT['largepro'] ?? 2), 1)."</select>".($lang['text_by_system_upon_uploading'] ?? '').'<br />'.($lang['text_large_torrent_promotion_note'] ?? '');
    @endphp
    {!! \App\Support\Html::tr($lang['row_large_torrent_promotion'] ?? 'Large torrent', $largeHtml, 1) !!}
    @php
    $expireFields = [
        ['halfleechbecome', 'expirehalfleech', 1, 5, 'text_halfleech_will_become', 'text_halfleech_timeout_default', 150],
        ['freebecome', 'expirefree', 1, 2, 'text_free_will_become', 'text_free_timeout_default', 60],
        ['twoupbecome', 'expiretwoup', 1, 3, 'text_twoup_will_become', 'text_twoup_timeout_default', 60],
        ['twoupfreebecome', 'expiretwoupfree', 1, 4, 'text_freetwoup_will_become', 'text_freetwoup_timeout_default', 30],
        ['twouphalfleechbecome', 'expiretwouphalfleech', 1, 6, 'text_halfleechtwoup_will_become', 'text_halfleechtwoup_timeout_default', 30],
        ['thirtypercentleechbecome', 'expirethirtypercentleech', 1, 7, 'text_thirtypercentleech_will_become', 'text_thirtypercentleech_timeout_default', 30],
        ['normalbecome', 'expirenormal', 1, 0, 'text_normal_will_become', 'text_normal_timeout_default', 0],
    ];
    $expireHtml = ($lang['text_promotion_timeout_note_one'] ?? '').'<ul>';
    foreach ($expireFields as [$become, $expire, $defBecome, $hide, $willKey, $defKey, $defExpire]) {
        $expireHtml .= '<li>'.($lang[$willKey] ?? '')."<select name={$become}>".\App\Support\Html::promotionSelection((int)($TORRENT[$become] ?? $defBecome), $hide)."</select>".($lang['text_after'] ?? ' after ')."<input type='text' style=\"width: 50px\" name={$expire} value='".htmlspecialchars((string)($TORRENT[$expire] ?? $defExpire))."'>".($lang[$defKey] ?? '').'</li>';
    }
    $expireHtml .= '</ul>'.($lang['text_promotion_timeout_note_two'] ?? '');
    @endphp
    {!! \App\Support\Html::tr($lang['row_promotion_timeout'] ?? 'Promotion timeout', $expireHtml, 1) !!}
    {!! $textRow($lang['row_auto_pick_hot'] ?? 'Auto pick hot', 'hotdays', $TORRENT['hotdays'] ?? 7, $lang['text_days_with_more_than'] ?? '', '50px') !!}
    {!! $textRow($lang['row_auto_pick_hot'] ?? '', 'hotseeder', $TORRENT['hotseeder'] ?? 10, $lang['text_be_picked_as_hot'] ?? '', '50px') !!}
    {!! $textRow($lang['row_uploader_get_double'] ?? 'Uploader double', 'uploaderdouble', $TORRENT['uploaderdouble'] ?? 1, $lang['text_times_uploading_credit'] ?? '', '50px') !!}
    {!! $textRow($lang['row_delete_dead_torrents'] ?? 'Delete dead', 'deldeadtorrent', $TORRENT['deldeadtorrent'] ?? 0, $lang['text_days_be_deleted'] ?? '', '50px') !!}
    {!! \App\Support\Html::tr($lang['row_save_settings'] ?? 'Save', "<input type='submit' name='save' value='".($lang['submit_save_settings'] ?? 'Save')."'>", 1) !!}
    </form>

@elseif ($action === 'attachmentsettings')
    @php $ATTACHMENT = $config ?? []; @endphp
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_attachment">@csrf
    {!! $yesorno($lang['row_enable_attachment'] ?? 'Enable attachment', 'enableattach', $ATTACHMENT['enableattach'] ?? 'no', $lang['text_enable_attachment_note'] ?? '') !!}
    @php
    $levels = [
        ['one', User::CLASS_USER, 'text_authority_default_one_one', 'text_authority_default_one_two'],
        ['two', User::CLASS_POWER_USER, 'text_authority_default_two_one', 'text_authority_default_two_two'],
        ['three', User::CLASS_ELITE_USER, '', ''],
        ['four', User::CLASS_EXTREME_USER, '', ''],
    ];
    $attachHtml = '<ul>';
    foreach ($levels as [$num, $defaultClass, $defOne, $defTwo]) {
        $cls = $classSelect("class{$num}", User::CLASS_STAFF_LEADER, $ATTACHMENT["class{$num}"] ?? 0);
        $attachHtml .= '<li>'.$cls.($lang['text_can_upload_at_most'] ?? '')."<input type='text' style=\"width: 50px\" name=\"count{$num}\" value='".htmlspecialchars((string)($ATTACHMENT["count{$num}"] ?? ''))."'> ".($lang['text_file_size_below'] ?? '')."<input type='text' style=\"width: 50px\" name=\"size{$num}\" value='".htmlspecialchars((string)($ATTACHMENT["size{$num}"] ?? ''))."'>".($lang['text_with_extension_name'] ?? '')."<input type='text' style=\"width: 200px\" name=\"ext{$num}\" value='".htmlspecialchars((string)($ATTACHMENT["ext{$num}"] ?? ''))."'>".($lang[$defOne] ?? '').\App\Support\UserClass::name($defaultClass, false, true, true).($lang[$defTwo] ?? '').'</li>';
    }
    $attachHtml .= '</ul>';
    @endphp
    {!! \App\Support\Html::tr($lang['row_attachment_authority'] ?? 'Attachment authority', $attachHtml, 1) !!}
    {!! $textRow($lang['row_save_directory'] ?? 'Save dir', 'savedirectory', $ATTACHMENT['savedirectory'] ?? './attachments', $lang['text_save_directory_note'] ?? '', '300px') !!}
    {!! $textRow($lang['row_http_directory'] ?? 'HTTP dir', 'httpdirectory', $ATTACHMENT['httpdirectory'] ?? 'attachments', $lang['text_http_directory_note'] ?? '', '300px') !!}
    @php
    $dirTypeRadio = '';
    foreach (['onedir' => 'text_one_directory', 'monthdir' => 'text_directories_by_monthes', 'daydir' => 'text_directories_by_days'] as $val => $label) {
        $checked = ($ATTACHMENT['savedirectorytype'] ?? 'onedir') === $val ? ' checked' : '';
        $dirTypeRadio .= "<input type='radio' name='savedirectorytype' value='{$val}'{$checked}>".($lang[$label] ?? $val).'<br />';
    }
    @endphp
    {!! \App\Support\Html::tr($lang['row_save_directory_type'] ?? 'Dir type', $dirTypeRadio.($lang['text_save_directory_type_note'] ?? ''), 1) !!}
    @php
    $thumbRadio = '';
    foreach (['no' => 'text_no_thumbnail', 'createthumb' => 'text_create_thumbnail', 'resizebigimg' => 'text_resize_big_image'] as $val => $label) {
        $checked = ($ATTACHMENT['thumbnailtype'] ?? 'no') === $val ? ' checked' : '';
        $thumbRadio .= "<input type='radio' name='thumbnailtype' value='{$val}'{$checked}> ".($lang[$label] ?? $val).'<br>';
    }
    @endphp
    {!! \App\Support\Html::tr($lang['row_image_thumbnails'] ?? 'Thumbnails', $thumbRadio.($lang['text_image_thumbnail_note'] ?? ''), 1) !!}
    {!! $textRow($lang['row_thumbnail_quality'] ?? 'Thumb quality', 'thumbquality', $ATTACHMENT['thumbquality'] ?? 80, $lang['text_thumbnail_quality_note'] ?? '', '100px') !!}
    <tr><td class="rowhead nowrap">{{ $lang['row_thumbnail_size'] ?? 'Thumb size' }}</td><td><input type='text' style="width: 100px" name="thumbwidth" value="{{ htmlspecialchars((string)($ATTACHMENT['thumbwidth'] ?? 500)) }}"> * <input type='text' style="width: 100px" name="thumbheight" value="{{ htmlspecialchars((string)($ATTACHMENT['thumbheight'] ?? 500)) }}"> {{ $lang['text_thumbnail_size_note'] ?? '' }}</td></tr>
    <tr><td class="rowhead nowrap">{{ $lang['row_alternative_thumbnail_size'] ?? 'Alt thumb size' }}</td><td><input type='text' style="width: 100px" name="altthumbwidth" value="{{ htmlspecialchars((string)($ATTACHMENT['altthumbwidth'] ?? 180)) }}"> * <input type='text' style="width: 100px" name="altthumbheight" value="{{ htmlspecialchars((string)($ATTACHMENT['altthumbheight'] ?? 135)) }}"> {{ $lang['text_alternative_thumbnail_size_note'] ?? '' }}</td></tr>
    {!! \App\Support\Html::tr($lang['row_save_settings'] ?? 'Save', "<input type='submit' name='save' value='".($lang['submit_save_settings'] ?? 'Save')."'>", 1) !!}
    </form>

@elseif ($action === 'codesettings')
    @php $CODE = $config ?? []; @endphp
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_code">@csrf
    {!! $textRow($lang['row_main_version'] ?? 'Main version', 'mainversion', $CODE['mainversion'] ?? 'NexusPHP', $lang['text_main_version_note'] ?? '', '300px') !!}
    {!! $textRow($lang['row_sub_version'] ?? 'Sub version', 'subversion', $CODE['subversion'] ?? '1.0', $lang['text_sub_version_note'] ?? '', '300px') !!}
    {!! $textRow($lang['row_release_date'] ?? 'Release date', 'releasedate', $CODE['releasedate'] ?? '2008-12-10', $lang['text_release_date_note'] ?? '', '300px') !!}
    {!! $textRow($lang['row_web_site'] ?? 'Website', 'website', $CODE['website'] ?? '', $lang['text_web_site_note_two'] ?? '', '300px') !!}
    {!! \App\Support\Html::tr($lang['row_save_settings'] ?? 'Save', "<input type='submit' name='save' value='".($lang['submit_save_settings'] ?? 'Save')."'>", 1) !!}
    </form>

@elseif ($action === 'miscsettings')
    @php $misc = $config ?? []; @endphp
    <form method="post" action="{{ $scriptName }}"><input type="hidden" name="action" value="savesettings_misc">@csrf
    <tr><td class="rowhead nowrap" valign="top">{{ $lang['row_misc_donation_custom'] ?? 'Donation custom' }}</td><td><textarea cols="100" rows="10" name='donation_custom'>{{ htmlspecialchars((string)($misc['donation_custom'] ?? '')) }}</textarea><br/>{{ $lang['text_donation_custom_note'] ?? '' }}</td></tr>
    {!! $textRow($lang['row_protected_forum'] ?? 'Protected forum', 'protected_forum', $misc['protected_forum'] ?? '', $lang['text_protected_forum'] ?? '', '100px') !!}
    {!! \App\Support\Html::tr($lang['row_save_settings'] ?? 'Save', "<input type='submit' name='save' value='".($lang['submit_save_settings'] ?? 'Save')."'>", 1) !!}
    </form>

@endif

</table>
@endsection
