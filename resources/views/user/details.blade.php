@extends('layouts.legacy')

@section('title', (__('legacy/userdetails.head_details_for')).$user['username'])

@section('content')
<h1 style="margin:0px">{{ $usernameHtml }}{{ $countryHtml }}</h1>
@if ($medalImagesHtml !== '')
{{ $medalImagesHtml }}
@endif
@if (! \App\Support\LegacyYesNo::isYes($user['enabled'] ?? null))
<p><b>{{ __('legacy/userdetails.text_account_disabled_note') ?? '' }}</b></p>
@elseif (! $isOwner)
@if ($isFriend)
<p>(<a href="friends.php?action=delete&amp;type=friend&amp;targetid={{ $id }}">{{ __('legacy/userdetails.text_remove_from_friends') ?? '' }}</a>)</p>
@elseif ($currentUserBlockedTarget)
<p>(<a href="friends.php?action=delete&amp;type=block&amp;targetid={{ $id }}">{{ __('legacy/userdetails.text_remove_from_blocks') ?? '' }}</a>)</p>
@else
<p>(<a href="friends.php?action=add&amp;type=friend&amp;targetid={{ $id }}">{{ __('legacy/userdetails.text_add_to_friends') ?? '' }}</a>) - (<a href="friends.php?action=add&amp;type=block&amp;targetid={{ $id }}">{{ __('legacy/userdetails.text_add_to_blocks') ?? '' }}</a>)</p>
@endif
@endif
@if ($isOwner || $canManageConfidential)
<h2>{{ __('legacy/userdetails.text_flush_ghost_torrents') ?? '' }}<a class="altlink" href="takeflush.php?id={{ $id }}">{{ __('legacy/userdetails.text_here') ?? '' }}</a></h2>
@endif
<table data-nx="data" width="100%" border="1" cellspacing="0" cellpadding="5">
@if (($user['privacy'] ?? '') !== 'strong' || $canManageBasic || $isOwner)
<x-settings-row-small :label="__('legacy/userdetails.text_user_id')">{{ $user['id'] }}@if ($canManageBasic && (int) $user['class'] < $currentClass)&nbsp;[<a href="{{ $userManageSystemUrl }}" target="_blank" class="altlink">{{ __('legacy/functions.text_management_system') ?? '' }}</a>]@endif</x-settings-row-small>
@if ($isOwner || $canViewInvite)
@if ((int) $user['invites'] <= 0 && $temporaryInviteCount <= 0)
<x-settings-row-small :label="__('legacy/userdetails.row_invitation')">{{ __('legacy/userdetails.text_no_invitation') ?? '' }}</x-settings-row-small>
@else
<x-settings-row-small :label="__('legacy/userdetails.row_invitation')"><a href="invite.php?id={{ $user['id'] }}" title="{{ __('legacy/userdetails.link_send_invitation') ?? '' }}">{{ $user['invites'] }}({{ $temporaryInviteCount }})</a></x-settings-row-small>
@endif
@else
@if ((int) $user['invites'] <= 0)
<x-settings-row-small :label="__('legacy/userdetails.row_invitation')">{{ __('legacy/userdetails.text_no_invitation') ?? '' }}</x-settings-row-small>
@else
<x-settings-row :label="__('legacy/userdetails.row_invitation')">{{ $user['invites'] }}</x-settings-row>
@endif
@endif
@if ((int) ($user['invited_by'] ?? 0) > 0)
<x-settings-row-small :label="__('legacy/userdetails.row_invited_by')">{{ $invitedByHtml }}</x-settings-row-small>
@endif
<x-settings-row-small :label="__('legacy/userdetails.row_join_date')">@if (($user['added'] ?? null) === null || $user['added'] === '0000-00-00 00:00:00'){{ __('legacy/userdetails.text_not_available') ?? '' }}@else{{ $user['added'] }} ({{ \App\Support\Time::format($user['added'], true, false, true) }}, {{ $joinWeeks }})@endif</x-settings-row-small>
<x-settings-row-small :label="__('legacy/userdetails.row_last_seen')">@if (($user['last_access'] ?? null) === null || $user['last_access'] === '0000-00-00 00:00:00'){{ __('legacy/userdetails.text_not_available') ?? '' }}@else{{ $user['last_access'] }} ({{ \App\Support\Time::format($user['last_access'], true, false, true) }})@endif</x-settings-row-small>
@if (($where_tweak ?? '') === 'yes')
<x-settings-row-small :label="__('legacy/userdetails.row_last_seen_location')">{{ $user['page'] }}</x-settings-row-small>
@endif
@if ($canViewConfidential || ($user['privacy'] ?? '') === 'low' || $isOwner)
<x-settings-row-small :label="__('legacy/userdetails.row_email')"><a href="mailto:{{ $user['email'] }}">{{ $user['email'] }}</a></x-settings-row-small>
@endif
@if ($canViewConfidential && $ipHistoryCount > 0)
<x-settings-row-small :label="__('legacy/userdetails.row_ip_history')">{{ __('legacy/userdetails.text_user_earlier_used') ?? '' }}<b><a href="iphistory.php?id={{ $user['id'] }}">{{ $ipHistoryCount }}{{ __('legacy/userdetails.text_different_ips') ?? '' }}{{ \App\Support\Strings::addS($ipHistoryCount, true) }}</a></b></x-settings-row-small>
@endif
@if ($canViewConfidential || $isOwner)
<x-settings-row-small :label="__('legacy/userdetails.row_ip_address')">{{ \App\Support\Strings::hidden((string) $user['ip'].$locationInfoHtml) }}</x-settings-row-small>
@endif
@if ($clientSelectHtml !== '')
<x-settings-row-small :label="__('legacy/userdetails.row_bt_client')">{{ $clientSelectHtml }}</x-settings-row-small>
@endif
<x-settings-row-small :label="__('legacy/userdetails.row_transfer')"><table data-nx="data" border="0" cellspacing="0" cellpadding="0">@if ($shareRatio !== null)<tr><td class="embedded"><strong>{{ __('legacy/userdetails.row_share_ratio') ?? '' }}</strong>:  <font color="{{ \App\Support\Ratio::color($shareRatio) }}">{{ number_format($shareRatio, 3) }}</font>（<strong>{{ __('legacy/userdetails.row_real_share_ratio') ?? '' }}</strong>：{{ number_format($trueRatio, 3) }}）</td><td class="embedded">&nbsp;&nbsp;{{ \App\Support\Ratio::image($shareRatio) }}</td></tr>@endif<tr><td class="embedded"><strong>{{ __('legacy/userdetails.row_uploaded') ?? '' }}</strong>:  {{ \App\Support\Format::size((float) $user['uploaded']) }}</td><td class="embedded">&nbsp;&nbsp;<strong>{{ __('legacy/userdetails.row_downloaded') ?? '' }}</strong>:  {{ \App\Support\Format::size((float) $user['downloaded']) }}</td></tr><tr><td class="embedded"><strong>{{ __('legacy/userdetails.row_real_uploaded') ?? '' }}</strong>:  {{ \App\Support\Format::size($trueUpload) }}</td><td class="embedded">&nbsp;&nbsp;<strong>{{ __('legacy/userdetails.row_real_downloaded') ?? '' }}</strong>:  {{ \App\Support\Format::size($trueDownload) }}</td><td class="embedded text-muted">&nbsp;&nbsp;{{ __('legacy/userdetails.row_real_ps') ?? '' }}</td></tr></table></x-settings-row-small>
<x-settings-row-small :label="__('legacy/userdetails.row_sltime')"><table data-nx="data" border="0" cellspacing="0" cellpadding="0">@if ($seedLeechRatio !== null)<tr><td class="embedded"><strong>{{ __('legacy/userdetails.text_seeding_leeching_time_ratio') ?? '' }}</strong>:  <font color="{{ \App\Support\Ratio::color($seedLeechRatio) }}">{{ number_format($seedLeechRatio, 3) }}</font></td><td class="embedded">&nbsp;&nbsp;{{ \App\Support\Ratio::image($seedLeechRatio) }}</td></tr>@endif<tr><td class="embedded"><strong>{{ __('legacy/userdetails.text_seeding_time') ?? '' }}</strong>:  {{ \App\Support\Format::prettyTimeWithLocale((int) $user['seedtime']) }}</td><td class="embedded">&nbsp;&nbsp;<strong>{{ __('legacy/userdetails.text_leeching_time') ?? '' }}</strong>:  {{ \App\Support\Format::prettyTimeWithLocale((int) $user['leechtime']) }}</td><td class="embedded text-muted">&nbsp;&nbsp;({{ \App\Support\Locale::trans('label.updated_at', [], null) }}: {{ $user['seed_time_updated_at'] }})</td></tr></table></x-settings-row-small>
<x-settings-row-small :label="__('legacy/userdetails.row_gender')">@if (($user['gender'] ?? '') === 'Male')<img class='male' src='pic/trans.gif' alt='Male' title='{{ __('legacy/userdetails.title_male') ?? '' }}'="{{ __('legacy/userdetails.title_male') }}" style='margin-left: 4pt' />@elseif (($user['gender'] ?? '') === 'Female')<img class='female' src='pic/trans.gif' alt='Female' title='{{ __('legacy/userdetails.title_female') ?? '' }}'="{{ __('legacy/userdetails.title_female') }}" style='margin-left: 4pt' />@elseif (($user['gender'] ?? '') === 'N/A')<img class='no_gender' src='pic/trans.gif' alt='N/A' title='{{ __('legacy/userdetails.title_not_available') ?? '' }}'="{{ __('legacy/userdetails.title_not_available') }}" style='margin-left: 4pt' />@endif</x-settings-row-small>
@if (((float) ($user['donated'] ?? 0) > 0 || (float) ($user['donated_cny'] ?? 0) > 0) && ($canViewConfidential || $isOwner))
<x-settings-row-small :label="__('legacy/userdetails.row_donated')">${{ $user['donated'] }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $user['donated_cny'] }}</x-settings-row-small>
@endif
@if (! empty($user['avatar']))
<x-settings-row-small :label="__('legacy/userdetails.row_avatar')">{{ $avatarHtml }}</x-settings-row-small>
@endif
<x-settings-row-small :label="__('legacy/userdetails.row_class')"><img alt="{{ \App\Support\UserClass::name($user['class'], false, false, true) }}" title="{{ \App\Support\UserClass::name($user['class'], false, false, true) }}" src="{{ \App\Support\UserClass::imagePath($user['class']) }}" />@if (($user['title'] ?? '') !== '')&nbsp;{{ trim($user['title']) }}@endif @if ((int) $user['class'] === UC_VIP && ! empty($user['vip_until']) && strtotime((string) $user['vip_until'])){{ __('legacy/userdetails.row_vip_until') ?? '' }}: {{ $user['vip_until'] }}@endif</x-settings-row-small>
@if ($userPropsHtml !== '')
<x-settings-row-small :label="__('legacy/userdetails.row_user_props')">{{ $userPropsHtml }}</x-settings-row-small>
@endif
<x-settings-row-small :label="__('legacy/userdetails.row_torrent_comment')">@if ($torrentcomments && ($isOwner || $canViewHistory))<a href="userhistory.php?action=viewcomments&amp;id={{ $id }}" title="{{ __('legacy/userdetails.link_view_comments') ?? '' }}">{{ $torrentcomments }}</a>@else{{ $torrentcomments }}@endif</x-settings-row-small>
<x-settings-row-small :label="__('legacy/userdetails.row_forum_posts')">@if ($forumposts && ($isOwner || $canViewHistory))<a href="userhistory.php?action=viewposts&amp;id={{ $id }}" title="{{ __('legacy/userdetails.link_view_posts') ?? '' }}">{{ $forumposts }}</a>@else{{ $forumposts }}@endif</x-settings-row-small>
@if ($isOwner || $canViewHistory)
@if ($hrStatusHtml !== '')
<x-settings-row-small label="H&R"><a href="myhr.php?userid={{ $user['id'] }}" target="_blank">{{ $hrStatusHtml }}</a></x-settings-row-small>
@endif
<x-settings-row-small :label="__('legacy/userdetails.row_karma_points')">{{ number_format((float) $user['seedbonus'], 1) }}&nbsp;&nbsp;<a href="bonus-log.php?uid={{ $user['id'] }}" target="_blank" class="altlink">[{{ \App\Support\Locale::trans('bonus-log.view_detail', [], null) }}]</a></x-settings-row-small>
<x-settings-row-small :label="__('legacy/functions.text_seed_points')">{{ number_format((float) $user['seed_points'], 1) }}&nbsp;&nbsp;<span class='text-muted'>({{ \App\Support\Locale::trans('label.updated_at', [], null) }}: {{ $user['seed_points_updated_at'] }})</span></x-settings-row-small>
@endif
@if ($canManageBasic && (int) $user['class'] < $currentClass && $bonusTableHtml !== '')
<x-settings-row-small :label="__('legacy/userdetails.text_bonus_table')">{{ $bonusTableHtml }}</x-settings-row-small>
@endif
@if (! empty($user['ip']) && ($canViewTorrentHistory || $isOwner))
<x-user.details-toggle :label="__('legacy/userdetails.row_uploaded_torrents')" type="uploaded" block="ka" imgId="pica" klappe="a" :userId="$user['id']" :title="__('legacy/userdetails.title_show_or_hide')" :linkText="__('legacy/userdetails.text_show_or_hide')" />
<x-user.details-toggle :label="__('legacy/userdetails.row_current_seeding')" type="seeding" block="ka1" imgId="pica1" klappe="a1" :userId="$user['id']" :title="__('legacy/userdetails.title_show_or_hide')" :linkText="__('legacy/userdetails.text_show_or_hide')" />
<x-user.details-toggle :label="__('legacy/userdetails.row_current_leeching')" type="leeching" block="ka2" imgId="pica2" klappe="a2" :userId="$user['id']" :title="__('legacy/userdetails.title_show_or_hide')" :linkText="__('legacy/userdetails.text_show_or_hide')" />
<x-user.details-toggle :label="__('legacy/userdetails.row_completed_torrents')" type="completed" block="ka3" imgId="pica3" klappe="a3" :userId="$user['id']" :title="__('legacy/userdetails.title_show_or_hide')" :linkText="__('legacy/userdetails.text_show_or_hide')" />
<x-user.details-toggle :label="__('legacy/userdetails.row_incomplete_torrents')" type="incomplete" block="ka4" imgId="pica4" klappe="a4" :userId="$user['id']" :title="__('legacy/userdetails.title_show_or_hide')" :linkText="__('legacy/userdetails.text_show_or_hide')" />
@endif
@if (! empty($user['info']))
<tr><td align="left" colspan="2" class="text">{{ \App\Support\Format::formatComment($user['info'], false) }}</td></tr>
@endif
@else
<tr><td align="left" colspan="2" class="text"><font color="blue">{{ __('legacy/userdetails.text_public_access_denied') ?? '' }}{{ $user['username'] }}{{ __('legacy/userdetails.text_user_wants_privacy') ?? '' }}</font></td></tr>
@endif
@if (! $isOwner)
<tr><td colspan="2" align="center">@if ($showPmButton)<a href="sendmessage.php?receiver={{ $user['id'] }}"><img class="f_pm" src="pic/trans.gif" alt="PM" title="{{ __('legacy/userdetails.title_send_pm') ?? '' }}" /></a>@endif<a href="report.php?user={{ $user['id'] }}"><img class="f_report" src="pic/trans.gif" alt="Report" title="{{ __('legacy/userdetails.title_report_user') ?? '' }}" /></a></td></tr>
@endif
</table>

@if ($canManageBasic && (int) $user['class'] < $currentClass)
<x-frame :caption="__('legacy/userdetails.text_edit_user')">
<form method="post" action="modtask.php">
<input type="hidden" name="action" value="edituser" />
<input type="hidden" name="userid" value="{{ $id }}" />
<input type="hidden" name="returnto" value="userdetails.php?id={{ $id }}" />
<table data-nx="data" width="100%" class="main" border="1" cellspacing="0" cellpadding="5">
<x-settings-row :label="__('legacy/userdetails.row_title')"><input type="text" size="60" name="title" value="{{ trim((string) $user['title']) }}" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_privacy_level')"><input type="radio" name="privacy" value="low"@if (($user['privacy'] ?? '') === 'low') checked="checked"@endif />{{ __('legacy/userdetails.radio_low') ?? '' }}<input type="radio" name="privacy" value="normal"@if (($user['privacy'] ?? '') === 'normal') checked="checked"@endif />{{ __('legacy/userdetails.radio_normal') ?? '' }}<input type="radio" name="privacy" value="strong"@if (($user['privacy'] ?? '') === 'strong') checked="checked"@endif />{{ __('legacy/userdetails.radio_strong') ?? '' }}</x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_avatar_url')"><input type="text" size="60" name="avatar" value="{{ trim((string) $user['avatar']) }}" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_signature')"><textarea cols="60" rows="6" name="signature">{{ trim((string) $user['signature']) }}</textarea></x-settings-row>
@if ($currentClass === UC_STAFFLEADER)
<x-settings-row :label="__('legacy/userdetails.row_donor_status')"><x-user.radio-yesno name="donor" :yes="\App\Support\LegacyYesNo::isYes($user['donor'] ?? null)" :no="\App\Support\LegacyYesNo::isNo($user['donor'] ?? null)" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_donated')">USD: <input type="text" size="5" name="donated" value="{{ $user['donated'] }}" />&nbsp;&nbsp;&nbsp;&nbsp;CNY: <input type="text" size="5" name="donated_cny" value="{{ $user['donated_cny'] }}" />{{ __('legacy/userdetails.text_transaction_memo') ?? '' }}<input type="text" size="50" name="donation_memo" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_donoruntil')"><input type="text" name="donoruntil" value="{{ $user['donoruntil'] }}" /> {{ __('legacy/userdetails.text_donoruntil_note') ?? '' }}</x-settings-row>
@endif
@if ($canChangeClass)
<x-settings-row :label="__('legacy/userdetails.row_class')">{{ $classSelectHtml }}{{ $migratedHelp }}</x-settings-row>
@endif
<x-settings-row :label="__('legacy/userdetails.row_vip_by_bonus')"><x-user.radio-yesno name="vip_added" :yes="\App\Support\LegacyYesNo::isYes($user['vip_added'] ?? null)" :no="\App\Support\LegacyYesNo::isNo($user['vip_added'] ?? null)" :disabled="true" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" />{{ $migratedHelp }}</x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_vip_until')"><input type="text" name="vip_until" value="{{ $user['vip_until'] }}" disabled='disabled' /> {{ __('legacy/userdetails.text_vip_until_note') ?? '' }}{{ $migratedHelp }}</x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_staff_duties')"><textarea cols="60" rows="6" name="staffduties">{{ $user['stafffor'] }}</textarea></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_support_language')"><input type="text" name="supportlang" value="{{ $user['supportlang'] }}" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_support')"><x-user.radio-yesno name="support" :yes="\App\Support\LegacyYesNo::isYes($user['support'] ?? null)" :no="\App\Support\LegacyYesNo::isNo($user['support'] ?? null)" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_support_for')"><textarea cols="60" rows="6" name="supportfor">{{ $user['supportfor'] }}</textarea></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_movie_picker')"><x-user.radio-yesno name="moviepicker" :yes="\App\Support\LegacyYesNo::isYes($user['picker'] ?? null)" :no="! \App\Support\LegacyYesNo::isYes($user['picker'] ?? null)" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_pick_for')"><textarea cols="60" rows="6" name="pickfor">{{ $user['pickfor'] }}</textarea></x-settings-row>
@if ($canManageConfidential)
<x-settings-row :label="__('legacy/userdetails.row_comment')"><textarea cols="60" rows="6" name="modcomment">{{ $modcomment }}</textarea></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_seeding_karma')"><textarea cols="60" rows="6" name="bonuscomment" readonly="readonly">{{ $bonuscomment }}</textarea></x-settings-row>
@endif
<tr><td class="rowhead">{{ __('legacy/userdetails.row_warning_system') }}<br /><br />{{ __('legacy/userdetails.row_warning_system_note') }}</td><td class="rowfollow" align="left" ><table data-nx="data" class="main" cellspacing="0" cellpadding="5"><tr><td class="rowfollow">@if ($warned)<input name="warned" value="yes" type="radio" checked="checked" />{{ __('legacy/userdetails.radio_yes') ?? '' }}<input name="warned" value="no" type="radio" />{{ __('legacy/userdetails.radio_no') ?? '' }}@else{{ __('legacy/userdetails.text_not_warned') ?? '' }}@endif</td>
@if ($warned)
@if ($warnedUntilPretty === null)
<td align="center" class="rowfollow">{{ __('legacy/userdetails.text_arbitrary_duration') ?? '' }}</td>
@else
<td align="left" class="rowfollow">{{ __('legacy/userdetails.text_until') ?? '' }}{{ $user['warneduntil'] }}<br />({{ $warnedUntilPretty }}{{ __('legacy/userdetails.text_to_go') ?? '' }})</td>
@endif
</tr>
@else
<td align="left" class="rowfollow">{{ __('legacy/userdetails.text_warn_for') ?? '' }}<select name="warnlength">
<option value="0">------</option>
<option value="1">1 {{ __('legacy/userdetails.text_week') ?? '' }}</option>
<option value="2">2 {{ __('legacy/userdetails.text_weeks') ?? '' }}</option>
<option value="4">4 {{ __('legacy/userdetails.text_weeks') ?? '' }}</option>
<option value="8">8 {{ __('legacy/userdetails.text_weeks') ?? '' }}</option>
<option value="255">{{ __('legacy/userdetails.text_unlimited') ?? '' }}</option>
</select></td></tr>
<tr><td align="left" class="rowfollow">{{ __('legacy/userdetails.text_reason_of_warning') ?? '' }}</td><td align="left" class="rowfollow"><input type="text" size="60" name="warnpm" /></td></tr>
@endif
<tr><td align="left" class="rowfollow">{{ __('legacy/userdetails.text_times_warned') ?? '' }}</td><td align="left" class="rowfollow">{{ $user['timeswarned'] }}</td></tr>
@if ((int) $user['timeswarned'] === 0)
<tr><td align="left" class="rowfollow">{{ __('legacy/userdetails.text_last_warning') ?? '' }}</td><td align="left" class="rowfollow">{{ __('legacy/userdetails.text_not_warned_note') ?? '' }}</td></tr>
@else
@if (($user['warnedby'] ?? '') === 'System')
<tr><td class="rowfollow">{{ __('legacy/userdetails.text_last_warning') ?? '' }}</td><td align="left" class="rowfollow"> {{ $user['lastwarned'] }} .({{ __('legacy/userdetails.text_until') ?? '' }}{{ $elapsedLastWarn }})   <br />[{{ __('legacy/userdetails.text_by_system') ?? '' }}]</td></tr>
@endif
<tr><td class="rowfollow">{{ __('legacy/userdetails.text_last_warning') ?? '' }}</td><td align="left" class="rowfollow"> {{ $user['lastwarned'] }} ({{ $elapsedLastWarn }}{{ __('legacy/userdetails.text_ago') ?? '' }})   @if (($user['warnedby'] ?? '') !== 'System'){{ $warnedByHtml }}@else<br />[{{ __('legacy/userdetails.text_by_system') ?? '' }}]@endif</td></tr>
@endif
<tr><td class="rowfollow">{{ __('legacy/userdetails.row_auto_warning') ?? '' }}<br /><i>({{ __('legacy/userdetails.text_low_ratio') ?? '' }})</i></td>
@if ($leechwarn)
<td align="left" class="rowfollow"><font color="red">{{ __('legacy/userdetails.text_leech_warned') ?? '' }}</font> {{ __('legacy/userdetails.text_until') ?? '' }}{{ $user['leechwarnuntil'] }}<br />({{ $leechwarnUntilPretty }}{{ __('legacy/userdetails.text_to_go') ?? '' }})&nbsp;<input id="remove-leech-warn" type="button" class="btn" value="Remove" data-uid="{{ $user['id'] }}" /></td></tr>
@else
<td class="rowfollow">{{ __('legacy/userdetails.text_not_warned') ?? '' }}</td></tr>
@endif
</table></td></tr>
<x-settings-row :label="__('legacy/userdetails.row_enabled')">{{ $migratedHelp }}</x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_forum_post_possible')"><x-user.radio-yesno name="forumpost" :yes="\App\Support\LegacyYesNo::isYes($user['forumpost'] ?? null)" :no="\App\Support\LegacyYesNo::isNo($user['forumpost'] ?? null)" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_upload_possible')"><x-user.radio-yesno name="uploadpos" :yes="\App\Support\LegacyYesNo::isYes($user['uploadpos'] ?? null)" :no="\App\Support\LegacyYesNo::isNo($user['uploadpos'] ?? null)" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_download_possible')"><x-user.radio-yesno name="downloadpos" :yes="\App\Support\LegacyYesNo::isYes($user['downloadpos'] ?? null)" :no="\App\Support\LegacyYesNo::isNo($user['downloadpos'] ?? null)" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" /></x-settings-row>
@if ($canManageConfidential)
<x-settings-row :label="__('legacy/userdetails.row_change_username')"><input type="text" size="25" name="username" value="{{ $user['username'] }}" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_change_email')"><input type="text" size="80" name="email" value="{{ $user['email'] }}" /></x-settings-row>
@endif
<x-settings-row :label="__('legacy/userdetails.row_change_password')"><input disabled type="password" name="chpassword" size="50" />{{ $migratedHelp }}</x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_repeat_password')"><input disabled type="password" name="passagain" size="50" />{{ $migratedHelp }}</x-settings-row>
@if ($canManageConfidential)
<x-settings-row :label="__('legacy/userdetails.row_amount_uploaded')"><input disabled type="text" size="60" name="uploaded" value="{{ $user['uploaded'] }}" /><input type="hidden" name="ori_uploaded" value="{{ $user['uploaded'] }}" />{{ $migratedHelp }}</x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_amount_downloaded')"><input disabled type="text" size="60" name="downloaded" value="{{ $user['downloaded'] }}" /><input type="hidden" name="ori_downloaded" value="{{ $user['downloaded'] }}" />{{ $migratedHelp }}</x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_seeding_karma')"><input disabled type="text" size="60" name="bonus" value="{{ number_format((float) $user['seedbonus'], 1) }}" /><input type="hidden" name="ori_bonus" value="{{ number_format((float) $user['seedbonus'], 1) }}" />{{ $migratedHelp }}</x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_invites')"><input disabled type="text" size="60" name="invites" value="{{ $user['invites'] }}" />{{ $migratedHelp }}</x-settings-row>
@endif
<x-settings-row :label="__('legacy/userdetails.row_passkey')"><input name="resetkey" value="yes" type="checkbox" />{{ __('legacy/userdetails.checkbox_reset_passkey') ?? '' }}</x-settings-row>
<tr><td class="toolbox" colspan="2" align="center"><input type="submit" class="class="btn" value="{{ __('legacy/userdetails.submit_okay') ?? '' }}"" /></td></tr>
</table>
</form>
</x-frame>
@if ($canDeleteUser)
<x-frame :caption="__('legacy/userdetails.text_delete_user')">
<form method="post" action="delacctadmin.php" name="deluser">
<input name="userid" size="10" type="hidden" value="{{ $user['id'] }}" />
<input name="delenable" type="checkbox" data-del-msg="{{ __('legacy/userdetails.js_delete_user_note') ?? '' }}" /><input name="submit" type="type="submit" value="{{ __('legacy/userdetails.submit_delete') ?? '' }}"" disabled="disabled" /></form>
</x-frame>
@endif
@endif
@endsection
