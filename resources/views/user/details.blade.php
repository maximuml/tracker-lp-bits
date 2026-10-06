@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', (__('legacy/userdetails.head_details_for')).$user['username'])

@section('content')
<h1>{{ $usernameHtml }}<img src="pic/flag/{{ $countryFlagPic }}" alt="{{ $countryName }}" /></h1>
@if (! \App\Support\LegacyYesNo::isYes($user['enabled'] ?? null))
<p><b>{{ __('legacy/userdetails.text_account_disabled_note') ?? '' }}</b></p>
@elseif (! $isOwner)
@if ($isFriend)
<p>(<form method="post" action="/web/friends/delete" class="nx-inline">@csrf<input type="hidden" name="type" value="friend" /><input type="hidden" name="targetid" value="{{ $id }}" /><button type="submit" class="nxm-linkbtn">{{ __('legacy/userdetails.text_remove_from_friends') ?? '' }}</button></form>)</p>
@elseif ($currentUserBlockedTarget)
<p>(<form method="post" action="/web/friends/delete" class="nx-inline">@csrf<input type="hidden" name="type" value="block" /><input type="hidden" name="targetid" value="{{ $id }}" /><button type="submit" class="nxm-linkbtn">{{ __('legacy/userdetails.text_remove_from_blocks') ?? '' }}</button></form>)</p>
@else
<p>(<form method="post" action="/web/friends/add" class="nx-inline">@csrf<input type="hidden" name="type" value="friend" /><input type="hidden" name="targetid" value="{{ $id }}" /><button type="submit" class="nxm-linkbtn">{{ __('legacy/userdetails.text_add_to_friends') ?? '' }}</button></form>) - (<form method="post" action="/web/friends/add" class="nx-inline">@csrf<input type="hidden" name="type" value="block" /><input type="hidden" name="targetid" value="{{ $id }}" /><button type="submit" class="nxm-linkbtn">{{ __('legacy/userdetails.text_add_to_blocks') ?? '' }}</button></form>)</p>
@endif
@endif
@if ($isOwner || $canManageConfidential)
<h2>{{ __('legacy/userdetails.text_flush_ghost_torrents') ?? '' }}<form method="post" action="/web/torrents/flush?id={{ $id }}" class="nx-inline">@csrf<button type="submit" class="nxm-linkbtn altlink">{{ __('legacy/userdetails.text_here') ?? '' }}</button></form></h2>
@endif
<x-data-table :caption="__('legacy/userdetails.head_details_for') . ' ' . $user['username']" captionHidden>
@if (($user['privacy'] ?? '') !== 'strong' || $canManageBasic || $isOwner)
<x-settings-row-small :label="__('legacy/userdetails.text_user_id')">{{ $user['id'] }}@if ($canManageBasic && (int) $user['class'] < $currentClass)&nbsp;[<a href="{{ $userManageSystemUrl }}" target="_blank" class="altlink">{{ __('legacy/functions.text_management_system') ?? '' }}</a>]@endif</x-settings-row-small>
@if ($isOwner || $canViewInvite)
@if ((int) $user['invites'] <= 0 && $temporaryInviteCount <= 0)
<x-settings-row-small :label="__('legacy/userdetails.row_invitation')">{{ __('legacy/userdetails.text_no_invitation') ?? '' }}</x-settings-row-small>
@else
<x-settings-row-small :label="__('legacy/userdetails.row_invitation')"><a href="/web/invite?id={{ $user['id'] }}" title="{{ __('legacy/userdetails.link_send_invitation') ?? '' }}">{{ $user['invites'] }}({{ $temporaryInviteCount }})</a></x-settings-row-small>
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
<x-settings-row-small :label="__('legacy/userdetails.row_join_date')">@if (($user['added'] ?? null) === null || $user['added'] === '0000-00-00 00:00:00'){{ __('legacy/userdetails.text_not_available') ?? '' }}@else{{ $user['added'] }} (<x-time :value="$user['added']" :force="true" />, {{ $joinWeeks }})@endif</x-settings-row-small>
<x-settings-row-small :label="__('legacy/userdetails.row_last_seen')">@if (($user['last_access'] ?? null) === null || $user['last_access'] === '0000-00-00 00:00:00'){{ __('legacy/userdetails.text_not_available') ?? '' }}@else{{ $user['last_access'] }} (<x-time :value="$user['last_access']" :force="true" />)@endif</x-settings-row-small>
@if (($where_tweak ?? '') === 'yes')
<x-settings-row-small :label="__('legacy/userdetails.row_last_seen_location')">{{ $user['page'] }}</x-settings-row-small>
@endif
@if ($canViewConfidential || ($user['privacy'] ?? '') === 'low' || $isOwner)
<x-settings-row-small :label="__('legacy/userdetails.row_email')"><a href="mailto:{{ $user['email'] }}">{{ $user['email'] }}</a></x-settings-row-small>
@endif
@if ($canViewConfidential && $ipHistoryCount > 0)
<x-settings-row-small :label="__('legacy/userdetails.row_ip_history')">{{ __('legacy/userdetails.text_user_earlier_used') ?? '' }}<b><a href="/iphistory?id={{ $user['id'] }}">{{ $ipHistoryCount }}{{ __('legacy/userdetails.text_different_ips') ?? '' }}{{ \App\Support\Strings::addS($ipHistoryCount, true) }}</a></b></x-settings-row-small>
@endif
@if ($canViewConfidential || $isOwner)
<x-settings-row-small :label="__('legacy/userdetails.row_ip_address')">{{ \App\Support\Strings::hidden((string) $user['ip'].$locationInfoHtml) }}</x-settings-row-small>
@endif
@if ($clientSelectHtml !== '')
<x-settings-row-small :label="__('legacy/userdetails.row_bt_client')">{{ $clientSelectHtml }}</x-settings-row-small>
@endif
<x-settings-row-small :label="__('legacy/userdetails.row_transfer')"><x-data-table :caption="__('legacy/userdetails.row_transfer')" captionHidden>@if ($shareRatio !== null)<tr><td class="nx-embedded"><strong>{{ __('legacy/userdetails.row_share_ratio') ?? '' }}</strong>:  <span class="{{ \App\Support\Ratio::colorClass($shareRatio) }}">{{ number_format($shareRatio, 3) }}</span>(<strong>{{ __('legacy/userdetails.row_real_share_ratio') ?? '' }}</strong>: {{ number_format($trueRatio, 3) }})</td><td class="nx-embedded">&nbsp;&nbsp;{{ \App\Support\Ratio::image($shareRatio) }}</td></tr>@endif<tr><td class="nx-embedded"><strong>{{ __('legacy/userdetails.row_uploaded') ?? '' }}</strong>:  {{ \App\Support\Format::size((float) $user['uploaded']) }}</td><td class="nx-embedded">&nbsp;&nbsp;<strong>{{ __('legacy/userdetails.row_downloaded') ?? '' }}</strong>:  {{ \App\Support\Format::size((float) $user['downloaded']) }}</td></tr><tr><td class="nx-embedded"><strong>{{ __('legacy/userdetails.row_real_uploaded') ?? '' }}</strong>:  {{ \App\Support\Format::size($trueUpload) }}</td><td class="nx-embedded">&nbsp;&nbsp;<strong>{{ __('legacy/userdetails.row_real_downloaded') ?? '' }}</strong>:  {{ \App\Support\Format::size($trueDownload) }}</td><td class="nx-embedded text-muted">&nbsp;&nbsp;{{ __('legacy/userdetails.row_real_ps') ?? '' }}</td></tr></x-data-table></x-settings-row-small>
<x-settings-row-small :label="__('legacy/userdetails.row_sltime')"><x-data-table :caption="__('legacy/userdetails.row_sltime')" captionHidden>@if ($seedLeechRatio !== null)<tr><td class="nx-embedded"><strong>{{ __('legacy/userdetails.text_seeding_leeching_time_ratio') ?? '' }}</strong>:  <span class="{{ \App\Support\Ratio::colorClass($seedLeechRatio) }}">{{ number_format($seedLeechRatio, 3) }}</span></td><td class="nx-embedded">&nbsp;&nbsp;{{ \App\Support\Ratio::image($seedLeechRatio) }}</td></tr>@endif<tr><td class="nx-embedded"><strong>{{ __('legacy/userdetails.text_seeding_time') ?? '' }}</strong>:  {{ \App\Support\Format::prettyTimeWithLocale((int) $user['seedtime']) }}</td><td class="nx-embedded">&nbsp;&nbsp;<strong>{{ __('legacy/userdetails.text_leeching_time') ?? '' }}</strong>:  {{ \App\Support\Format::prettyTimeWithLocale((int) $user['leechtime']) }}</td><td class="nx-embedded text-muted">@if (! empty($user['seed_time_updated_at']))&nbsp;&nbsp;({{ \App\Support\Locale::trans('label.updated_at', [], null) }}: {{ $user['seed_time_updated_at'] }})@endif</td></tr></x-data-table></x-settings-row-small>
<x-settings-row-small :label="__('legacy/userdetails.row_gender')">@if (($user['gender'] ?? '') === 'Male')<img class='male' src='pic/trans.gif' alt='Male' title='{{ __('legacy/userdetails.title_male') ?? '' }}' />@elseif (($user['gender'] ?? '') === 'Female')<img class='female' src='pic/trans.gif' alt='Female' title='{{ __('legacy/userdetails.title_female') ?? '' }}' />@elseif (($user['gender'] ?? '') === 'N/A')<img class='no_gender' src='pic/trans.gif' alt='N/A' title='{{ __('legacy/userdetails.title_not_available') ?? '' }}' />@endif</x-settings-row-small>
@if (((float) ($user['donated'] ?? 0) > 0 || (float) ($user['donated_cny'] ?? 0) > 0) && ($canViewConfidential || $isOwner))
<x-settings-row-small :label="__('legacy/userdetails.row_donated')">${{ $user['donated'] }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $user['donated_cny'] }}</x-settings-row-small>
@endif
@if (! empty($user['avatar']))
<x-settings-row-small :label="__('legacy/userdetails.row_avatar')">{{ $avatarHtml }}</x-settings-row-small>
@endif
<x-settings-row-small :label="__('legacy/userdetails.row_class')"><img alt="{{ \App\Support\UserClass::name($user['class'], false, false, true) }}" title="{{ \App\Support\UserClass::name($user['class'], false, false, true) }}" src="{{ \App\Support\UserClass::imagePath($user['class']) }}" />@if (($user['title'] ?? '') !== '')&nbsp;{{ trim($user['title']) }}@endif @if ((int) $user['class'] === UC_VIP && ! empty($user['vip_until']) && strtotime((string) $user['vip_until'])){{ __('legacy/userdetails.row_vip_until') ?? '' }}: {{ $user['vip_until'] }}@endif</x-settings-row-small>
@if ($userProps !== [])
<x-settings-row-small :label="__('legacy/userdetails.row_user_props')"><div>@foreach ($userProps as $prop){{ $prop }}@unless ($loop->last)&nbsp;|&nbsp;@endunless @endforeach</div></x-settings-row-small>
@endif
<x-settings-row-small :label="__('legacy/userdetails.row_torrent_comment')">@if ($torrentcomments && ($isOwner || $canViewHistory))<a href="/web/userhistory?action=viewcomments&amp;id={{ $id }}" title="{{ __('legacy/userdetails.link_view_comments') ?? '' }}">{{ $torrentcomments }}</a>@else{{ $torrentcomments }}@endif</x-settings-row-small>
<x-settings-row-small :label="__('legacy/userdetails.row_forum_posts')">@if ($forumposts && ($isOwner || $canViewHistory))<a href="/web/userhistory?action=viewposts&amp;id={{ $id }}" title="{{ __('legacy/userdetails.link_view_posts') ?? '' }}">{{ $forumposts }}</a>@else{{ $forumposts }}@endif</x-settings-row-small>
@if ($isOwner || $canViewHistory)
@if ($hrStatusHtml !== '')
<x-settings-row-small label="H&R"><a href="/web/myhr?userid={{ $user['id'] }}" target="_blank" aria-label="H&R stats">{{ $hrStatusHtml }}</a></x-settings-row-small>
@endif
<x-settings-row-small :label="__('legacy/userdetails.row_karma_points')">{{ number_format((float) $user['seedbonus'], 1) }}&nbsp;&nbsp;<a href="/web/bonus-log?uid={{ $user['id'] }}" target="_blank" class="altlink">[{{ \App\Support\Locale::trans('bonus-log.view_detail', [], null) }}]</a></x-settings-row-small>
<x-settings-row-small :label="__('legacy/functions.text_seed_points')">{{ number_format((float) $user['seed_points'], 1) }}&nbsp;&nbsp;@if (! empty($user['seed_points_updated_at']))<span class='text-muted'>({{ \App\Support\Locale::trans('label.updated_at', [], null) }}: {{ $user['seed_points_updated_at'] }})</span>@endif</x-settings-row-small>
@endif
@if ($canManageBasic && (int) $user['class'] < $currentClass && $bonusTableHtml !== '')
<x-settings-row-small :label="__('legacy/userdetails.text_bonus_table')">{{ $bonusTableHtml }}</x-settings-row-small>
@endif
@if (! empty($user['ip']) && ($canViewTorrentHistory || $isOwner))
<livewire:user-torrent-list :user-id="$user['id']" type="uploaded" :label="__('legacy/userdetails.row_uploaded_torrents')" :title="__('legacy/userdetails.title_show_or_hide')" :link-text="__('legacy/userdetails.text_show_or_hide')" />
<livewire:user-torrent-list :user-id="$user['id']" type="seeding" :label="__('legacy/userdetails.row_current_seeding')" :title="__('legacy/userdetails.title_show_or_hide')" :link-text="__('legacy/userdetails.text_show_or_hide')" />
<livewire:user-torrent-list :user-id="$user['id']" type="leeching" :label="__('legacy/userdetails.row_current_leeching')" :title="__('legacy/userdetails.title_show_or_hide')" :link-text="__('legacy/userdetails.text_show_or_hide')" />
<livewire:user-torrent-list :user-id="$user['id']" type="completed" :label="__('legacy/userdetails.row_completed_torrents')" :title="__('legacy/userdetails.title_show_or_hide')" :link-text="__('legacy/userdetails.text_show_or_hide')" />
<livewire:user-torrent-list :user-id="$user['id']" type="incomplete" :label="__('legacy/userdetails.row_incomplete_torrents')" :title="__('legacy/userdetails.title_show_or_hide')" :link-text="__('legacy/userdetails.text_show_or_hide')" />
@endif
@if (! empty($user['info']))
<tr><td colspan="2" class="p-[10pt]">{{ \App\Support\Format::formatComment($user['info'], false) }}</td></tr>
@endif
@else
<tr><td colspan="2" class="p-[10pt]"><span class="text-[#0000ff]">{{ __('legacy/userdetails.text_public_access_denied') ?? '' }}{{ $user['username'] }}{{ __('legacy/userdetails.text_user_wants_privacy') ?? '' }}</span></td></tr>
@endif
@if (! $isOwner)
<tr><td colspan="2" class="text-center">@if ($showPmButton)<a href="/web/sendmessage?receiver={{ $user['id'] }}"><img class="f_pm" src="pic/trans.gif" alt="PM" title="{{ __('legacy/userdetails.title_send_pm') ?? '' }}" /></a>@endif<a href="/web/report?user={{ $user['id'] }}"><img class="f_report" src="pic/trans.gif" alt="Report" title="{{ __('legacy/userdetails.title_report_user') ?? '' }}" /></a></td></tr>
@endif
</x-data-table>

@if ($canManageBasic && (int) $user['class'] < $currentClass)
<x-frame :caption="__('legacy/userdetails.text_edit_user')" :center="false">
<form method="post" action="/web/staff/modtask">@csrf
<input type="hidden" name="action" value="edituser" />
<input type="hidden" name="userid" value="{{ $id }}" />
<input type="hidden" name="returnto" value="/userdetails?id={{ $id }}" />
<x-data-table :caption="__('legacy/userdetails.text_edit_user')" captionHidden class="nx-main">
<x-settings-row :label="__('legacy/userdetails.row_title')"><input type="text" size="60" name="title" value="{{ trim((string) $user['title']) }}" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_privacy_level')"><input type="radio" name="privacy" value="low"@if (($user['privacy'] ?? '') === 'low') checked="checked"@endif />{{ __('legacy/userdetails.radio_low') ?? '' }}<input type="radio" name="privacy" value="normal"@if (($user['privacy'] ?? '') === 'normal') checked="checked"@endif />{{ __('legacy/userdetails.radio_normal') ?? '' }}<input type="radio" name="privacy" value="strong"@if (($user['privacy'] ?? '') === 'strong') checked="checked"@endif />{{ __('legacy/userdetails.radio_strong') ?? '' }}</x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_avatar_url')"><input type="text" size="60" name="avatar" value="{{ trim((string) $user['avatar']) }}" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_signature')"><textarea cols="60" rows="6" name="signature">{{ trim((string) $user['signature']) }}</textarea></x-settings-row>
@if ($currentClass === UC_STAFFLEADER)
<x-settings-row :label="__('legacy/userdetails.row_donor_status')"><x-user.radio-yesno name="donor" :yes="\App\Support\LegacyYesNo::isYes($user['donor'] ?? null)" :no="\App\Support\LegacyYesNo::isNo($user['donor'] ?? null)" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_donated')">USD: <input type="text" size="5" name="donated" value="{{ $user['donated'] }}" />&nbsp;&nbsp;&nbsp;&nbsp;CNY: <input type="text" size="5" name="donated_cny" value="{{ $user['donated_cny'] }}" />{{ __('legacy/userdetails.text_transaction_memo') ?? '' }}<input type="text" size="50" name="donation_memo" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_donoruntil')"><input type="text" name="donoruntil" value="{{ $user['donoruntil'] }}" /> {{ __('legacy/userdetails.text_donoruntil_note') ?? '' }}</x-settings-row>
@endif
<x-settings-row :label="__('legacy/functions.text_management_system')">{{ $migratedHelp }}</x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_staff_duties')"><textarea cols="60" rows="6" name="staffduties">{{ $user['stafffor'] }}</textarea></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_support_language')"><input type="text" name="supportlang" value="{{ $user['supportlang'] }}" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_support')"><x-user.radio-yesno name="support" :yes="\App\Support\LegacyYesNo::isYes($user['support'] ?? null)" :no="\App\Support\LegacyYesNo::isNo($user['support'] ?? null)" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_support_for')"><textarea cols="60" rows="6" name="supportfor">{{ $user['supportfor'] }}</textarea></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_movie_picker')"><x-user.radio-yesno name="moviepicker" :yes="\App\Support\LegacyYesNo::isYes($user['picker'] ?? null)" :no="! \App\Support\LegacyYesNo::isYes($user['picker'] ?? null)" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_pick_for')"><textarea cols="60" rows="6" name="pickfor">{{ $user['pickfor'] }}</textarea></x-settings-row>
@if ($canManageConfidential)
<x-settings-row :label="__('legacy/userdetails.row_comment')"><textarea cols="60" rows="6" name="modcomment" placeholder="{{ $modcomment }}"></textarea></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_seeding_karma')"><textarea cols="60" rows="6" name="bonuscomment" readonly="readonly">{{ $bonuscomment }}</textarea></x-settings-row>
@endif
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('legacy/userdetails.row_warning_system') }}<br /><br />{{ __('legacy/userdetails.row_warning_system_note') }}</td><td class="align-top px-2.5 py-1.5"><x-data-table :caption="__('legacy/userdetails.row_warning_system')" captionHidden class="nx-main"><tr><td class="align-top px-2.5 py-1.5">@if ($warned)<input name="warned" value="yes" type="radio" checked="checked" />{{ __('legacy/userdetails.radio_yes') ?? '' }}<input name="warned" value="no" type="radio" />{{ __('legacy/userdetails.radio_no') ?? '' }}@else{{ __('legacy/userdetails.text_not_warned') ?? '' }}@endif</td>
@if ($warned)
@if ($warnedUntilPretty === null)
<td class="align-top px-2.5 py-1.5 text-center">{{ __('legacy/userdetails.text_arbitrary_duration') ?? '' }}</td>
@else
<td class="align-top px-2.5 py-1.5">{{ __('legacy/userdetails.text_until') ?? '' }}{{ $user['warneduntil'] }}<br />({{ $warnedUntilPretty }}{{ __('legacy/userdetails.text_to_go') ?? '' }})</td>
@endif
</tr>
@else
<td class="align-top px-2.5 py-1.5">{{ __('legacy/userdetails.text_warn_for') ?? '' }}<select name="warnlength">
<option value="0">------</option>
<option value="1">1 {{ __('legacy/userdetails.text_week') ?? '' }}</option>
<option value="2">2 {{ __('legacy/userdetails.text_weeks') ?? '' }}</option>
<option value="4">4 {{ __('legacy/userdetails.text_weeks') ?? '' }}</option>
<option value="8">8 {{ __('legacy/userdetails.text_weeks') ?? '' }}</option>
<option value="255">{{ __('legacy/userdetails.text_unlimited') ?? '' }}</option>
</select></td></tr>
<tr><td class="align-top px-2.5 py-1.5">{{ __('legacy/userdetails.text_reason_of_warning') ?? '' }}</td><td class="align-top px-2.5 py-1.5"><input type="text" size="60" name="warnpm" /></td></tr>
@endif
<tr><td class="align-top px-2.5 py-1.5">{{ __('legacy/userdetails.text_times_warned') ?? '' }}</td><td class="align-top px-2.5 py-1.5">{{ $user['timeswarned'] }}</td></tr>
@if ((int) $user['timeswarned'] === 0)
<tr><td class="align-top px-2.5 py-1.5">{{ __('legacy/userdetails.text_last_warning') ?? '' }}</td><td class="align-top px-2.5 py-1.5">{{ __('legacy/userdetails.text_not_warned_note') ?? '' }}</td></tr>
@else
@if (($user['warnedby'] ?? '') === 'System')
<tr><td class="align-top px-2.5 py-1.5">{{ __('legacy/userdetails.text_last_warning') ?? '' }}</td><td class="align-top px-2.5 py-1.5"> {{ $user['lastwarned'] }} .({{ __('legacy/userdetails.text_until') ?? '' }}{{ $elapsedLastWarn }})   <br />[{{ __('legacy/userdetails.text_by_system') ?? '' }}]</td></tr>
@endif
<tr><td class="align-top px-2.5 py-1.5">{{ __('legacy/userdetails.text_last_warning') ?? '' }}</td><td class="align-top px-2.5 py-1.5"> {{ $user['lastwarned'] }} ({{ $elapsedLastWarn }}{{ __('legacy/userdetails.text_ago') ?? '' }})   @if (($user['warnedby'] ?? '') !== 'System'){{ $warnedByHtml }}@else<br />[{{ __('legacy/userdetails.text_by_system') ?? '' }}]@endif</td></tr>
@endif
<tr><td class="align-top px-2.5 py-1.5">{{ __('legacy/userdetails.row_auto_warning') ?? '' }}<br /><i>({{ __('legacy/userdetails.text_low_ratio') ?? '' }})</i></td>
@if ($leechwarn)
<td class="align-top px-2.5 py-1.5"><span class="text-nxm-danger">{{ __('legacy/userdetails.text_leech_warned') ?? '' }}</span> {{ __('legacy/userdetails.text_until') ?? '' }}{{ $user['leechwarnuntil'] }}<br />({{ $leechwarnUntilPretty }}{{ __('legacy/userdetails.text_to_go') ?? '' }})&nbsp;<input id="remove-leech-warn" type="button" class="btn" value="Remove" data-uid="{{ $user['id'] }}" /></td></tr>
@else
<td class="align-top px-2.5 py-1.5">{{ __('legacy/userdetails.text_not_warned') ?? '' }}</td></tr>
@endif
</x-data-table></td></tr>
<x-settings-row :label="__('legacy/userdetails.row_forum_post_possible')"><x-user.radio-yesno name="forumpost" :yes="\App\Support\LegacyYesNo::isYes($user['forumpost'] ?? null)" :no="\App\Support\LegacyYesNo::isNo($user['forumpost'] ?? null)" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_upload_possible')"><x-user.radio-yesno name="uploadpos" :yes="\App\Support\LegacyYesNo::isYes($user['uploadpos'] ?? null)" :no="\App\Support\LegacyYesNo::isNo($user['uploadpos'] ?? null)" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_download_possible')"><x-user.radio-yesno name="downloadpos" :yes="\App\Support\LegacyYesNo::isYes($user['downloadpos'] ?? null)" :no="\App\Support\LegacyYesNo::isNo($user['downloadpos'] ?? null)" :yesLabel="__('legacy/userdetails.radio_yes')" :noLabel="__('legacy/userdetails.radio_no')" /></x-settings-row>
@if ($canManageConfidential)
<x-settings-row :label="__('legacy/userdetails.row_change_username')"><input type="text" size="25" name="username" value="{{ $user['username'] }}" /></x-settings-row>
<x-settings-row :label="__('legacy/userdetails.row_change_email')"><input type="text" size="80" name="email" value="{{ $user['email'] }}" /></x-settings-row>
@endif
<x-settings-row :label="__('legacy/userdetails.row_passkey')"><input name="resetkey" value="yes" type="checkbox" />{{ __('legacy/userdetails.checkbox_reset_passkey') ?? '' }}</x-settings-row>
<tr><td class="toolbox text-center" colspan="2"><input type="submit" class="btn" value="{{ __('legacy/userdetails.submit_okay') ?? '' }}" /></td></tr>
</x-data-table>
</form>
</x-frame>
@if ($canDeleteUser)
<x-frame :caption="__('legacy/userdetails.text_delete_user')">
<form method="post" action="delacctadmin.php" name="deluser">
<input name="userid" size="10" type="hidden" value="{{ $user['id'] }}" />
<input name="delenable" type="checkbox" data-del-msg="{{ __('legacy/userdetails.js_delete_user_note') ?? '' }}" /><input name="submit" type="submit" value="{{ __('legacy/userdetails.submit_delete') ?? '' }}" disabled="disabled" /></form>
</x-frame>
@endif
@endif
@endsection
