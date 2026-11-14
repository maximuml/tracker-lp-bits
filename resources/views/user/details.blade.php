@extends('layouts.app')

@section('title', (__('userdetails.head_details_for')).$vm->user['username'])

@section('content')
<x-page :title="$vm->usernameHtml" :subtitle="$vm->countryName" width="list">
@if (! \App\Support\YesNo::isYes($vm->user['enabled'] ?? null))
<p><b>{{ __('userdetails.text_account_disabled_note') }}</b></p>
@elseif (! $vm->isOwner)
@if ($vm->isFriend)
<div class="my-2">(<form method="post" action="/web/friends/delete" class="nx-inline">@csrf<input type="hidden" name="type" value="friend" /><input type="hidden" name="targetid" value="{{ $vm->id }}" /><button type="submit" class="nxm-linkbtn" data-submit-once>{{ __('userdetails.text_remove_from_friends') }}</button></form>)</div>
@elseif ($vm->currentUserBlockedTarget)
<div class="my-2">(<form method="post" action="/web/friends/delete" class="nx-inline">@csrf<input type="hidden" name="type" value="block" /><input type="hidden" name="targetid" value="{{ $vm->id }}" /><button type="submit" class="nxm-linkbtn" data-submit-once>{{ __('userdetails.text_remove_from_blocks') }}</button></form>)</div>
@else
<div class="my-2">(<form method="post" action="/web/friends/add" class="nx-inline">@csrf<input type="hidden" name="type" value="friend" /><input type="hidden" name="targetid" value="{{ $vm->id }}" /><button type="submit" class="nxm-linkbtn" data-submit-once>{{ __('userdetails.text_add_to_friends') }}</button></form>) - (<form method="post" action="/web/friends/add" class="nx-inline">@csrf<input type="hidden" name="type" value="block" /><input type="hidden" name="targetid" value="{{ $vm->id }}" /><button type="submit" class="nxm-linkbtn" data-submit-once>{{ __('userdetails.text_add_to_blocks') }}</button></form>)</div>
@endif
@endif
@if ($vm->isOwner || $vm->canManageConfidential)
<div><h2 class="nx-inline">{{ __('userdetails.text_flush_ghost_torrents') }}</h2> <form method="post" action="/web/torrents/flush?id={{ $vm->id }}" class="nx-inline">@csrf<button type="submit" class="nx-postbtn nx-postbtn--danger" data-confirm="{{ __('userdetails.confirm_flush_ghost') }}" data-submit-once>{{ __('userdetails.text_here') }}</button></form></div>
@endif
<x-data-table :caption="__('userdetails.head_details_for') . ' ' . $vm->user['username']" captionHidden>
@if (($vm->user['privacy'] ?? '') !== 'strong' || $vm->canManageBasic || $vm->isOwner)
<x-settings-row-small :label="__('userdetails.text_user_id')">{{ $vm->user['id'] }}@if ($vm->canManageBasic && (int) $vm->user['class'] < $vm->currentClass)&nbsp;[<a href="{{ $vm->userManageSystemUrl }}" target="_blank" rel="noopener" class="altlink">{{ __('functions.text_management_system') }}</a>]@endif</x-settings-row-small>
@if ($vm->isOwner || $vm->canViewInvite)
@if ((int) $vm->user['invites'] <= 0 && $vm->temporaryInviteCount <= 0)
<x-settings-row-small :label="__('userdetails.row_invitation')">{{ __('userdetails.text_no_invitation') }}</x-settings-row-small>
@else
<x-settings-row-small :label="__('userdetails.row_invitation')"><a href="/web/invite?id={{ $vm->user['id'] }}" title="{{ __('userdetails.link_send_invitation') }}">{{ $vm->user['invites'] }}({{ $vm->temporaryInviteCount }})</a></x-settings-row-small>
@endif
@else
@if ((int) $vm->user['invites'] <= 0)
<x-settings-row-small :label="__('userdetails.row_invitation')">{{ __('userdetails.text_no_invitation') }}</x-settings-row-small>
@else
<x-settings-row :label="__('userdetails.row_invitation')">{{ $vm->user['invites'] }}</x-settings-row>
@endif
@endif
@if ((int) ($vm->user['invited_by'] ?? 0) > 0)
<x-settings-row-small :label="__('userdetails.row_invited_by')">{{ $vm->invitedByHtml }}</x-settings-row-small>
@endif
<x-settings-row-small :label="__('userdetails.row_join_date')">@if (($vm->user['added'] ?? null) === null || $vm->user['added'] === '0000-00-00 00:00:00'){{ __('userdetails.text_not_available') }}@else{{ $vm->user['added'] }} (<x-time :value="$vm->user['added']" :force="true" />, {{ $vm->joinWeeks }})@endif</x-settings-row-small>
<x-settings-row-small :label="__('userdetails.row_last_seen')">@if (($vm->user['last_access'] ?? null) === null || $vm->user['last_access'] === '0000-00-00 00:00:00'){{ __('userdetails.text_not_available') }}@else{{ $vm->user['last_access'] }} (<x-time :value="$vm->user['last_access']" :force="true" />)@endif</x-settings-row-small>
@if (($where_tweak ?? '') === 'yes')
<x-settings-row-small :label="__('userdetails.row_last_seen_location')">{{ $vm->user['page'] }}</x-settings-row-small>
@endif
@if ($vm->canViewConfidential || ($vm->user['privacy'] ?? '') === 'low' || $vm->isOwner)
<x-settings-row-small :label="__('userdetails.row_email')"><a href="mailto:{{ $vm->user['email'] }}">{{ $vm->user['email'] }}</a></x-settings-row-small>
@endif
@if ($vm->canViewConfidential && $vm->ipHistoryCount > 0)
<x-settings-row-small :label="__('userdetails.row_ip_history')">{{ __('userdetails.text_user_earlier_used') }}<b><a href="/iphistory?id={{ $vm->user['id'] }}">{{ $vm->ipHistoryCount }}{{ __('userdetails.text_different_ips') }}{{ \App\Support\Strings::addS($vm->ipHistoryCount, true) }}</a></b></x-settings-row-small>
@endif
@if ($vm->canViewConfidential || $vm->isOwner)
<x-settings-row-small :label="__('userdetails.row_ip_address')">{{ \App\Support\Strings::hidden((string) $vm->user['ip'].$vm->locationInfoHtml) }}</x-settings-row-small>
@endif
@if ($vm->clientSelectHtml !== null)
<x-settings-row-small :label="__('userdetails.row_bt_client')">{{ $vm->clientSelectHtml }}</x-settings-row-small>
@endif
<x-settings-row-small :label="__('userdetails.row_transfer')"><x-data-table :caption="__('userdetails.row_transfer')" captionHidden>@if ($vm->shareRatio !== null)<tr><td class="nx-embedded"><strong>{{ __('userdetails.row_share_ratio') }}</strong>:  <x-status-pill :cls="\App\Support\Ratio::colorClass($vm->shareRatio)">{{ number_format($vm->shareRatio, 3) }}</x-status-pill>(<strong>{{ __('userdetails.row_real_share_ratio') }}</strong>: {{ number_format($vm->trueRatio, 3) }})</td><td class="nx-embedded">&nbsp;&nbsp;{{ \App\Support\Ratio::image($vm->shareRatio) }}</td></tr>@endif<tr><td class="nx-embedded"><strong>{{ __('userdetails.row_uploaded') }}</strong>:  {{ \App\Support\Format::size((float) $vm->user['uploaded']) }}</td><td class="nx-embedded">&nbsp;&nbsp;<strong>{{ __('userdetails.row_downloaded') }}</strong>:  {{ \App\Support\Format::size((float) $vm->user['downloaded']) }}</td></tr><tr><td class="nx-embedded"><strong>{{ __('userdetails.row_real_uploaded') }}</strong>:  {{ \App\Support\Format::size($vm->trueUpload) }}</td><td class="nx-embedded">&nbsp;&nbsp;<strong>{{ __('userdetails.row_real_downloaded') }}</strong>:  {{ \App\Support\Format::size($vm->trueDownload) }}</td><td class="nx-embedded text-muted">&nbsp;&nbsp;{{ __('userdetails.row_real_ps') }}</td></tr></x-data-table></x-settings-row-small>
<x-settings-row-small :label="__('userdetails.row_sltime')"><x-data-table :caption="__('userdetails.row_sltime')" captionHidden>@if ($vm->seedLeechRatio !== null)<tr><td class="nx-embedded"><strong>{{ __('userdetails.text_seeding_leeching_time_ratio') }}</strong>:  <x-status-pill :cls="\App\Support\Ratio::colorClass($vm->seedLeechRatio)">{{ number_format($vm->seedLeechRatio, 3) }}</x-status-pill></td><td class="nx-embedded">&nbsp;&nbsp;{{ \App\Support\Ratio::image($vm->seedLeechRatio) }}</td></tr>@endif<tr><td class="nx-embedded"><strong>{{ __('userdetails.text_seeding_time') }}</strong>:  {{ \App\Support\Format::prettyTimeWithLocale((int) $vm->user['seedtime']) }}</td><td class="nx-embedded">&nbsp;&nbsp;<strong>{{ __('userdetails.text_leeching_time') }}</strong>:  {{ \App\Support\Format::prettyTimeWithLocale((int) $vm->user['leechtime']) }}</td><td class="nx-embedded text-muted">@if (! empty($vm->user['seed_time_updated_at']))&nbsp;&nbsp;({{ \App\Support\Locale::trans('label.updated_at', [], null) }}: {{ $vm->user['seed_time_updated_at'] }})@endif</td></tr></x-data-table></x-settings-row-small>
<x-settings-row-small :label="__('userdetails.row_gender')">@if (($vm->user['gender'] ?? '') === 'Male')<i class='male' role='img' aria-label='{{ __('userdetails.title_male') }}' title='{{ __('userdetails.title_male') }}'></i>@elseif (($vm->user['gender'] ?? '') === 'Female')<i class='female' role='img' aria-label='{{ __('userdetails.title_female') }}' title='{{ __('userdetails.title_female') }}'></i>@elseif (($vm->user['gender'] ?? '') === 'N/A')<i class='no_gender' role='img' aria-label='{{ __('userdetails.title_not_available') }}' title='{{ __('userdetails.title_not_available') }}'></i>@endif</x-settings-row-small>
@if (((float) ($vm->user['donated'] ?? 0) > 0 || (float) ($vm->user['donated_cny'] ?? 0) > 0) && ($vm->canViewConfidential || $vm->isOwner))
<x-settings-row-small :label="__('userdetails.row_donated')">${{ $vm->user['donated'] }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $vm->user['donated_cny'] }}</x-settings-row-small>
@endif
@if (! empty($vm->user['avatar']))
<x-settings-row-small :label="__('userdetails.row_avatar')">{{ $vm->avatarHtml }}</x-settings-row-small>
@endif
<x-settings-row-small :label="__('userdetails.row_class')"><img class="nx-class-badge" alt="{{ \App\Support\UserClass::name($vm->user['class'], false, false, true) }}" title="{{ \App\Support\UserClass::name($vm->user['class'], false, false, true) }}" src="{{ \App\Support\UserClass::imagePath($vm->user['class']) }}" loading="lazy" decoding="async" />@if (($vm->user['title'] ?? '') !== '')&nbsp;{{ trim($vm->user['title']) }}@endif @if ((int) $vm->user['class'] === UC_VIP && ! empty($vm->user['vip_until']) && strtotime((string) $vm->user['vip_until'])){{ __('userdetails.row_vip_until') }}: {{ $vm->user['vip_until'] }}@endif</x-settings-row-small>
@if ($vm->userProps !== [])
<x-settings-row-small :label="__('userdetails.row_user_props')"><div>@foreach ($vm->userProps as $prop){{ $prop }}@unless ($loop->last)&nbsp;|&nbsp;@endunless @endforeach</div></x-settings-row-small>
@endif
<x-settings-row-small :label="__('userdetails.row_torrent_comment')">@if ($vm->torrentcomments && ($vm->isOwner || $vm->canViewHistory))<a href="/web/userhistory?action=viewcomments&amp;id={{ $vm->id }}" title="{{ __('userdetails.link_view_comments') }}">{{ $vm->torrentcomments }}</a>@else{{ $vm->torrentcomments }}@endif</x-settings-row-small>
<x-settings-row-small :label="__('userdetails.row_forum_posts')">@if ($vm->forumposts && ($vm->isOwner || $vm->canViewHistory))<a href="/web/userhistory?action=viewposts&amp;id={{ $vm->id }}" title="{{ __('userdetails.link_view_posts') }}">{{ $vm->forumposts }}</a>@else{{ $vm->forumposts }}@endif</x-settings-row-small>
@if ($vm->isOwner || $vm->canViewHistory)
@if ($vm->hrStatusHtml !== '')
<x-settings-row-small :label="__('userdetails.row_hr')"><a href="/web/myhr?userid={{ $vm->user['id'] }}" target="_blank" rel="noopener" aria-label="{{ __('userdetails.aria_hr_stats') }}">{{ $vm->hrStatusHtml }}</a></x-settings-row-small>
@endif
<x-settings-row-small :label="__('userdetails.row_karma_points')">{{ number_format((float) $vm->user['seedbonus'], 1) }}&nbsp;&nbsp;<a href="/web/bonus-log?uid={{ $vm->user['id'] }}" target="_blank" rel="noopener" class="altlink">[{{ \App\Support\Locale::trans('bonus-log.view_detail', [], null) }}]</a></x-settings-row-small>
<x-settings-row-small :label="__('functions.text_seed_points')">{{ number_format((float) $vm->user['seed_points'], 1) }}&nbsp;&nbsp;@if (! empty($vm->user['seed_points_updated_at']))<span class='text-muted'>({{ \App\Support\Locale::trans('label.updated_at', [], null) }}: {{ $vm->user['seed_points_updated_at'] }})</span>@endif</x-settings-row-small>
@endif
@if ($vm->canManageBasic && (int) $vm->user['class'] < $vm->currentClass && $vm->bonusTableHtml !== '')
<x-settings-row-small :label="__('userdetails.text_bonus_table')">{{ $vm->bonusTableHtml }}</x-settings-row-small>
@endif
@if (! empty($vm->user['ip']) && ($vm->canViewTorrentHistory || $vm->isOwner))
@foreach ($vm->torrentListSections as $section)
<tr><td class="w-[1%] whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ $section['label'] }}</td><td class="w-[99%] align-top px-2.5 py-1.5"><a href="#" data-nx-torrentlist data-userid="{{ $vm->user['id'] }}" data-type="{{ $section['type'] }}" aria-expanded="false"><i class="plus" role="img" aria-label="{{ __('functions.aria_show_hide') }}" title="{{ __('userdetails.title_show_or_hide') }}"></i>&nbsp;&nbsp;<u>{{ __('userdetails.text_show_or_hide') }}</u></a><i data-nx-torrentlist-loading hidden>{{ __('functions.text_loading') }}</i><div data-nx-torrentlist-body hidden></div></td></tr>
@endforeach
@endif
@if (! empty($vm->user['info']))
<tr><td colspan="2" class="p-[10pt]">{{ \App\Support\Format::formatComment($vm->user['info'], false) }}</td></tr>
@endif
@else
<tr><td colspan="2" class="p-[10pt]"><span class="text-nxm-accent-text">{{ __('userdetails.text_public_access_denied') }}{{ $vm->user['username'] }}{{ __('userdetails.text_user_wants_privacy') }}</span></td></tr>
@endif
@if (! $vm->isOwner)
<tr><td colspan="2" class="text-center">@if ($vm->showPmButton)<a href="/web/sendmessage?receiver={{ $vm->user['id'] }}"><i class="f_pm" role="img" aria-label="{{ __('userdetails.title_send_pm') }}" title="{{ __('userdetails.title_send_pm') }}"></i></a>@endif<a href="/web/report?user={{ $vm->user['id'] }}"><i class="f_report" role="img" aria-label="{{ __('userdetails.title_report_user') }}" title="{{ __('userdetails.title_report_user') }}"></i></a></td></tr>
@endif
</x-data-table>

@if ($vm->canManageBasic && (int) $vm->user['class'] < $vm->currentClass)
<x-frame :caption="__('userdetails.text_edit_user')" :center="false">
<form method="post" action="/web/staff/modtask">@csrf
<input type="hidden" name="action" value="edituser" />
<input type="hidden" name="userid" value="{{ $vm->id }}" />
<input type="hidden" name="returnto" value="/userdetails?id={{ $vm->id }}" />
<x-data-table :caption="__('userdetails.text_edit_user')" captionHidden class="nx-main">
<x-settings-row :label="__('userdetails.row_title')" for="mod-title"><input type="text" size="60" id="mod-title" name="title" value="{{ trim((string) $vm->user['title']) }}" /></x-settings-row>
<x-settings-row :label="__('userdetails.row_privacy_level')"><label><input type="radio" name="privacy" value="low"@if (($vm->user['privacy'] ?? '') === 'low') checked="checked"@endif />{{ __('userdetails.radio_low') }}</label><label><input type="radio" name="privacy" value="normal"@if (($vm->user['privacy'] ?? '') === 'normal') checked="checked"@endif />{{ __('userdetails.radio_normal') }}</label><label><input type="radio" name="privacy" value="strong"@if (($vm->user['privacy'] ?? '') === 'strong') checked="checked"@endif />{{ __('userdetails.radio_strong') }}</label></x-settings-row>
<x-settings-row :label="__('userdetails.row_avatar_url')" for="mod-avatar"><input type="text" size="60" id="mod-avatar" name="avatar" value="{{ trim((string) $vm->user['avatar']) }}" /></x-settings-row>
@if ($vm->currentClass === UC_STAFFLEADER)
<x-settings-row :label="__('userdetails.row_donor_status')"><x-user.radio-yesno name="donor" :yes="\App\Support\YesNo::isYes($vm->user['donor'] ?? null)" :no="\App\Support\YesNo::isNo($vm->user['donor'] ?? null)" :yesLabel="__('userdetails.radio_yes')" :noLabel="__('userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('userdetails.row_donated')"><label>USD: <input type="text" size="5" name="donated" value="{{ $vm->user['donated'] }}" /></label>&nbsp;&nbsp;&nbsp;&nbsp;<label>CNY: <input type="text" size="5" name="donated_cny" value="{{ $vm->user['donated_cny'] }}" /></label><label>{{ __('userdetails.text_transaction_memo') }}<input type="text" size="50" name="donation_memo" /></label></x-settings-row>
<x-settings-row :label="__('userdetails.row_donoruntil')" for="donoruntil"><input type="text" id="donoruntil" name="donoruntil" value="{{ $vm->user['donoruntil'] }}" /> {{ __('userdetails.text_donoruntil_note') }}</x-settings-row>
@endif
<x-settings-row :label="__('functions.text_management_system')">{{ $vm->migratedHelp }}</x-settings-row>
<x-settings-row :label="__('userdetails.row_staff_duties')" for="staffduties"><textarea cols="60" rows="6" id="staffduties" name="staffduties">{{ $vm->user['stafffor'] }}</textarea></x-settings-row>
<x-settings-row :label="__('userdetails.row_support_language')" for="supportlang"><input type="text" id="supportlang" name="supportlang" value="{{ $vm->user['supportlang'] }}" /></x-settings-row>
<x-settings-row :label="__('userdetails.row_support')"><x-user.radio-yesno name="support" :yes="\App\Support\YesNo::isYes($vm->user['support'] ?? null)" :no="\App\Support\YesNo::isNo($vm->user['support'] ?? null)" :yesLabel="__('userdetails.radio_yes')" :noLabel="__('userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('userdetails.row_support_for')" for="supportfor"><textarea cols="60" rows="6" id="supportfor" name="supportfor">{{ $vm->user['supportfor'] }}</textarea></x-settings-row>
@if ($vm->canManageConfidential)
<x-settings-row :label="__('userdetails.row_comment')" for="modcomment"><textarea cols="60" rows="6" id="modcomment" name="modcomment" placeholder="{{ $vm->modcomment }}"></textarea></x-settings-row>
<x-settings-row :label="__('userdetails.row_seeding_karma')" for="bonuscomment"><textarea cols="60" rows="6" id="bonuscomment" name="bonuscomment" readonly="readonly">{{ $vm->bonuscomment }}</textarea></x-settings-row>
@endif
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ __('userdetails.row_warning_system') }}<br /><br />{{ __('userdetails.row_warning_system_note') }}</td><td class="align-top px-2.5 py-1.5"><x-data-table :caption="__('userdetails.row_warning_system')" captionHidden class="nx-main"><tr><td class="align-top px-2.5 py-1.5">@if ($vm->warned)<label><input name="warned" value="yes" type="radio" checked="checked" />{{ __('userdetails.radio_yes') }}</label><label><input name="warned" value="no" type="radio" />{{ __('userdetails.radio_no') }}</label>@else{{ __('userdetails.text_not_warned') }}@endif</td>
@if ($vm->warned)
@if ($vm->warnedUntilPretty === null)
<td class="align-top px-2.5 py-1.5 text-center">{{ __('userdetails.text_arbitrary_duration') }}</td>
@else
<td class="align-top px-2.5 py-1.5">{{ __('userdetails.text_until') }}{{ $vm->user['warneduntil'] }}<br />({{ $vm->warnedUntilPretty }}{{ __('userdetails.text_to_go') }})</td>
@endif
</tr>
@else
<td class="align-top px-2.5 py-1.5"><label>{{ __('userdetails.text_warn_for') }}<select name="warnlength">
<option value="0">------</option>
<option value="1">1 {{ __('userdetails.text_week') }}</option>
<option value="2">2 {{ __('userdetails.text_weeks') }}</option>
<option value="4">4 {{ __('userdetails.text_weeks') }}</option>
<option value="8">8 {{ __('userdetails.text_weeks') }}</option>
<option value="255">{{ __('userdetails.text_unlimited') }}</option>
</select></label></td></tr>
<tr><td class="align-top px-2.5 py-1.5"><label for="warnpm">{{ __('userdetails.text_reason_of_warning') }}</label></td><td class="align-top px-2.5 py-1.5"><input type="text" size="60" id="warnpm" name="warnpm" /></td></tr>
@endif
<tr><td class="align-top px-2.5 py-1.5">{{ __('userdetails.text_times_warned') }}</td><td class="align-top px-2.5 py-1.5">{{ $vm->user['timeswarned'] }}</td></tr>
@if ((int) $vm->user['timeswarned'] === 0)
<tr><td class="align-top px-2.5 py-1.5">{{ __('userdetails.text_last_warning') }}</td><td class="align-top px-2.5 py-1.5">{{ __('userdetails.text_not_warned_note') }}</td></tr>
@else
@if (($vm->user['warnedby'] ?? '') === 'System')
<tr><td class="align-top px-2.5 py-1.5">{{ __('userdetails.text_last_warning') }}</td><td class="align-top px-2.5 py-1.5"> {{ $vm->user['lastwarned'] }} .({{ __('userdetails.text_until') }}{{ $vm->elapsedLastWarn }})   <br />[{{ __('userdetails.text_by_system') }}]</td></tr>
@endif
<tr><td class="align-top px-2.5 py-1.5">{{ __('userdetails.text_last_warning') }}</td><td class="align-top px-2.5 py-1.5"> {{ $vm->user['lastwarned'] }} ({{ $vm->elapsedLastWarn }}{{ __('userdetails.text_ago') }})   @if (($vm->user['warnedby'] ?? '') !== 'System'){{ $vm->warnedByHtml }}@else<br />[{{ __('userdetails.text_by_system') }}]@endif</td></tr>
@endif
<tr><td class="align-top px-2.5 py-1.5">{{ __('userdetails.row_auto_warning') }}<br /><i>({{ __('userdetails.text_low_ratio') }})</i></td>
@if ($vm->leechwarn)
<td class="align-top px-2.5 py-1.5"><span class="text-nxm-danger">{{ __('userdetails.text_leech_warned') }}</span> {{ __('userdetails.text_until') }}{{ $vm->user['leechwarnuntil'] }}<br />({{ $vm->leechwarnUntilPretty }}{{ __('userdetails.text_to_go') }})@if ($vm->canRemoveLeechWarn)&nbsp;<input id="remove-leech-warn" type="button" class="nx-btn nx-btn--primary" value="Remove" data-uid="{{ $vm->user['id'] }}" />@endif</td></tr>
@else
<td class="align-top px-2.5 py-1.5">{{ __('userdetails.text_not_warned') }}</td></tr>
@endif
</x-data-table></td></tr>
<x-settings-row :label="__('userdetails.row_forum_post_possible')"><x-user.radio-yesno name="forumpost" :yes="\App\Support\YesNo::isYes($vm->user['forumpost'] ?? null)" :no="\App\Support\YesNo::isNo($vm->user['forumpost'] ?? null)" :yesLabel="__('userdetails.radio_yes')" :noLabel="__('userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('userdetails.row_upload_possible')"><x-user.radio-yesno name="uploadpos" :yes="\App\Support\YesNo::isYes($vm->user['uploadpos'] ?? null)" :no="\App\Support\YesNo::isNo($vm->user['uploadpos'] ?? null)" :yesLabel="__('userdetails.radio_yes')" :noLabel="__('userdetails.radio_no')" /></x-settings-row>
<x-settings-row :label="__('userdetails.row_download_possible')"><x-user.radio-yesno name="downloadpos" :yes="\App\Support\YesNo::isYes($vm->user['downloadpos'] ?? null)" :no="\App\Support\YesNo::isNo($vm->user['downloadpos'] ?? null)" :yesLabel="__('userdetails.radio_yes')" :noLabel="__('userdetails.radio_no')" /></x-settings-row>
@if ($vm->canManageConfidential)
<x-settings-row :label="__('userdetails.row_change_username')" for="mod-username"><input type="text" size="25" id="mod-username" name="username" value="{{ $vm->user['username'] }}" /></x-settings-row>
<x-settings-row :label="__('userdetails.row_change_email')" for="mod-email"><input type="text" size="80" id="mod-email" name="email" value="{{ $vm->user['email'] }}" /></x-settings-row>
@endif
<x-settings-row :label="__('userdetails.row_passkey')"><label><input name="resetkey" value="yes" type="checkbox" />{{ __('userdetails.checkbox_reset_passkey') }}</label></x-settings-row>
<tr><td class="toolbox text-center" colspan="2"><input type="submit" class="nx-btn nx-btn--primary" value="{{ __('userdetails.submit_okay') }}"  data-submit-once /></td></tr>
</x-data-table>
</form>
</x-frame>
@endif
</x-page>
@endsection
