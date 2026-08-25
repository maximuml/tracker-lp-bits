@php
$lang_invite = (array) (\App\Support\SupportContext::getGlobal('lang_invite') ?? []);
$lang_functions = (array) (\App\Support\SupportContext::getGlobal('lang_functions') ?? []);
$CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
$id = (int) ($id ?? 0);
$type = (string) ($type ?? '');
$menuSelected = (string) ($menuSelected ?? 'invitee');
$user = (array) ($user ?? []);
$sent = (string) ($sent ?? '');
$__server_REQUEST_URI = (string) ($__server_REQUEST_URI ?? '');
$title = $title ?? ($lang_invite['head_invites'] ?? 'Invites');
$UC_SYSOP = (int) ($UC_SYSOP ?? \App\Models\User::CLASS_SYSOP);
$sendBtnText = (string) ($sendBtnText ?? '');
$sendBtnDisabled = (string) ($sendBtnDisabled ?? '');
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
<table width=100% class=main border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>

<h1 align=center><a href="invite.php?id={{ $id }}">{{ $user['username'] ?? '' }}{{ $lang_invite['text_invite_system'] ?? '' }}</a></h1>
@if ($sent == 1)
    <p align=center><font color=red>{{ $lang_invite['text_invite_code_sent'] ?? '' }}</font></p>
@endif

@php
    $inv = $inv ?? $user;
    $_s = $_s ?? (($inv['invites'] ?? 0) != 1 ? ($lang_invite['text_s'] ?? 's') : '');
@endphp

@if ($type == 'new')
    @php
        $SITENAME = $SITENAME ?? \App\Models\Setting::getSiteName();
        $temporaryInvites = $temporaryInvites ?? collect();
        $inviteSelectOptions = $inviteSelectOptions ?? '';
        $preUsernameTr = $preUsernameTr ?? '';
        $invitation_body = $invitation_body ?? sprintf($lang_invite['text_invitation_body'] ?? '', $SITENAME).($CURUSER['username'] ?? '');
    @endphp
    <form method=post action=takeinvite.php?id={{ htmlspecialchars((string) $id) }}>
    <table border=1 width=100% cellspacing=0 cellpadding=5>
    <tr align=center><td colspan=2><b>{{ $lang_invite['text_invite_someone'] ?? '' }}{{ $SITENAME }} ({{ $inv['invites'] ?? 0 }}{{ $lang_invite['text_invitation'] ?? '' }}{{ $_s }}{{ $lang_invite['text_left'] ?? '' }} + {{ sprintf($lang_invite['text_temporary_left'] ?? '%d', count($temporaryInvites)) }})</b></td></tr>
    <tr><td class="rowhead nowrap" valign="top" align="right">{{ $lang_invite['text_email_address'] ?? '' }}</td><td align=left><input type=text size=40 name=email><br /><font align=left class=small>{{ $lang_invite['text_email_address_note'] ?? '' }}</font></td></tr>
    {!! $preUsernameTr !!}
    <tr><td class="rowhead nowrap" valign="top" align="right">{{ $lang_invite['text_consume_invite'] ?? '' }}</td><td align=left><select name='hash'>{!! $inviteSelectOptions !!}</select></td></tr>
    <tr><td class="rowhead nowrap" valign="top" align="right">{{ $lang_invite['text_message'] ?? '' }}</td><td align=left><textarea name=body rows=10 style='width: 100%'>{{ $invitation_body }}</textarea></td></tr>
    <tr><td align=center colspan=2><input type=submit value='{{ $lang_invite['submit_invite'] ?? '' }}'></td></tr>
    </form></table></td></tr></table>

@else
    {{-- Invite menu nav --}}
    <div id="invitenav" style='position: relative'><ul id="invitemenu" class="menu">
    <li{{ $menuSelected == 'invitee' ? ' class=selected' : '' }}><a href="?id={{ $id }}&menu=invitee">{{ $lang_invite['text_invite_status'] ?? '' }}</a></li>
    <li{{ $menuSelected == 'sent' ? ' class=selected' : '' }}><a href="?id={{ $id }}&menu=sent">{{ $lang_invite['text_sent_invites_status'] ?? '' }}</a></li>
    <li{{ $menuSelected == 'tmp' ? ' class=selected' : '' }}><a href="?id={{ $id }}&menu=tmp">{{ $lang_invite['text_tmp_status'] ?? '' }}</a></li>
    @if (($CURUSER['id'] ?? 0) == $id)
        </ul><form style='position: absolute;top:0;right:0' method=post action=invite.php?id={{ htmlspecialchars((string) $id) }}&type=new><input type=submit{{ $sendBtnDisabled }} value='{{ $sendBtnText }}'></form></div>
    @else
        </ul></div>
    @endif

    @if ($menuSelected == 'invitee')
        @php
            $inviteeCount = (int) ($inviteeCount ?? 0);
            $inviteeRows = (array) ($inviteeRows ?? []);
            $inviteePagertop = (string) ($inviteePagertop ?? '');
            $inviteePagerbottom = (string) ($inviteePagerbottom ?? '');
            $inviteeEnabledOptions = (string) ($inviteeEnabledOptions ?? '');
            $inviteeStatusOptions = (string) ($inviteeStatusOptions ?? '');
            $haremAdditionFactor = (float) ($haremAdditionFactor ?? 0);
            $pendingCount = (int) ($pendingCount ?? 0);
            $textSelectOnePlease = (string) ($textSelectOnePlease ?? '');
            $resetText = (string) ($resetText ?? '');
            $submitText = (string) ($submitText ?? '');
            $enabled = (string) ($enabled ?? '');
            $status = (string) ($status ?? '');
        @endphp
        <div>
            <form id="filterForm" action="{{ $__server_REQUEST_URI }}" method="get">
                <input type="hidden" name="menu" value="invitee" />
                <input type="hidden" name="id" value="{{ $id }}" />
                <span>{{ $lang_invite['text_enabled'] ?? '' }}:</span>
                <select name="enabled">
                    <option value="">-{{ $textSelectOnePlease }}-</option>
                    {!! $inviteeEnabledOptions !!}
                </select>
                &nbsp;&nbsp;
                <span>{{ $lang_invite['text_status'] ?? '' }}:</span>
                <select name="status">
                    <option value="">-{{ $textSelectOnePlease }}-</option>
                    {!! $inviteeStatusOptions !!}
                </select>
                &nbsp;&nbsp;
                <input type="submit" value="{{ $submitText }}">
                <input type="button" id="reset" value="{{ $resetText }}">
            </form>
        </div>
        <table border=1 width=100% cellspacing=0 cellpadding=5>
        <form method=post action=takeconfirm.php?id={{ htmlspecialchars((string) $id) }}>

        @if (! $inviteeCount)
            <tr><td colspan=7 align=center>{{ $lang_invite['text_no_invites'] ?? '' }}</tr>
        @else
            <tr>
            <td class=colhead><b>{{ $lang_invite['text_username'] ?? '' }}</b></td>
            <td class=colhead><b>{{ $lang_invite['text_email'] ?? '' }}</b></td>
            <td class=colhead><b>{{ $lang_invite['text_enabled'] ?? '' }}</b></td>
            <td class=colhead><b>{{ $lang_invite['text_uploaded_count'] ?? '' }}</b></td>
            <td class=colhead><b>{{ $lang_invite['text_uploaded'] ?? '' }}</b></td>
            <td class=colhead><b>{{ $lang_invite['text_downloaded'] ?? '' }}</b></td>
            <td class=colhead><b>{{ $lang_invite['text_ratio'] ?? '' }}</b></td>
            <td class=colhead><b>{{ $lang_invite['text_seed_torrent_count'] ?? '' }}</b></td>
            <td class=colhead><b>{{ $lang_invite['text_seed_torrent_size'] ?? '' }}</b></td>
            <td class=colhead title="{{ $lang_invite['text_seed_torrent_bonus_per_hour_help'] ?? '' }}"><b>{{ $lang_invite['text_seed_torrent_bonus_per_hour'] ?? '' }}</b></td>
            @if ($haremAdditionFactor > 0)
                <td class="colhead">{{ $lang_invite['harem_addition'] ?? '' }}</td>
            @endif
            <td class=colhead><b>{{ $lang_invite['text_seed_torrent_last_announce_at'] ?? '' }}</b></td>
            <td class=colhead><b>{{ $lang_invite['text_status'] ?? '' }}</b></td>
            @if (($CURUSER['id'] ?? 0) == $id || \App\Support\UserDisplay::currentClass() >= $UC_SYSOP)
                <td class=colhead><b>{{ $lang_invite['text_confirm'] ?? '' }}</b></td>
            @endif
            </tr>
            @foreach ($inviteeRows as $arr)
                @php
                    if ($arr['downloaded'] > 0) {
                        $ratio = number_format($arr['uploaded'] / $arr['downloaded'], 3);
                        $ratio = '<font color='.\App\Support\Ratio::color($ratio).">$ratio</font>";
                    } else {
                        $ratio = $arr['uploaded'] > 0 ? 'Inf.' : '---';
                    }
                    if ($arr['status'] == 'confirmed') {
                        $statusHtml = "<a href=userdetails.php?id={$arr['id']}><font color=#1f7309>".($lang_invite['text_confirmed'] ?? '').'</font></a>';
                    } else {
                        $statusHtml = "<a href=checkuser.php?id={$arr['id']}><font color=#ca0226>".($lang_invite['text_pending'] ?? '').'</font></a>';
                    }
                @endphp
                <tr class=rowfollow>
                    <td class=rowfollow>{!! \App\Support\UserDisplay::username($arr['id']) !!}</td>
                    <td class=rowfollow>{{ $arr['email'] }}</td>
                    <td class=rowfollow>{{ $arr['enabled'] }}</td>
                    <td class=rowfollow>{{ $arr['torrent_count'] }}</td>
                    <td class=rowfollow>{{ \App\Support\Format::size($arr['uploaded']) }}</td>
                    <td class=rowfollow>{{ \App\Support\Format::size($arr['downloaded']) }}</td>
                    <td class=rowfollow>{!! $ratio !!}</td>
                    <td class=rowfollow>{{ number_format($arr['seeding_torrent_count']) }}</td>
                    <td class=rowfollow>{{ \App\Support\Format::size($arr['seeding_torrent_size']) }}</td>
                    <td class=rowfollow>{{ number_format($arr['seed_points_per_hour'], 3) }}</td>
                @if ($haremAdditionFactor > 0)
                    <td class=rowfollow>{{ number_format(floatval($arr['seed_points_per_hour']) * $haremAdditionFactor, 3) }}</td>
                @endif
                <td class=rowfollow>{{ $arr['last_announce_at'] }}</td>
                <td class=rowfollow>{!! $statusHtml !!}</td>
                @if (($CURUSER['id'] ?? 0) == $id || \App\Support\UserDisplay::currentClass() >= $UC_SYSOP)
                    <td class=rowfollow>
                    @if ($arr['status'] == 'pending')
                        <input type="checkbox" name="conusr[]" value="{{ $arr['id'] }}" />
                    @endif
                    </td>
                @endif
                </tr>
            @endforeach
        @endif

        @if (($CURUSER['id'] ?? 0) == $id || \App\Support\UserDisplay::currentClass() >= $UC_SYSOP)
            @php
                $colSpan = 12;
                if ($haremAdditionFactor > 0) { $colSpan += 1; }
            @endphp
            @if ($pendingCount)
                <tr><td colspan={{ $colSpan }} align=right><input type=submit style='height: 20px' value='{{ $lang_invite['submit_confirm_users'] ?? '' }}'></td></tr>
            @endif
            </form>
        @endif
        </table>
        </td></tr></table>{{ $inviteePagertop }}

    @elseif (in_array($menuSelected, ['sent', 'tmp'], true))
        @php
            $sentTmpCount = (int) ($sentTmpCount ?? 0);
            $sentTmpRows = (array) ($sentTmpRows ?? []);
            $sentTmpPagertop = (string) ($sentTmpPagertop ?? '');
        @endphp
        <table border=1 width=100% cellspacing=0 cellpadding=5>
        @if (! $sentTmpCount)
            <tr align=center><td colspan=6>{{ $lang_functions['text_none'] ?? '' }}</tr>
        @else
            <tr><td class=colhead>{{ $lang_invite['text_email'] ?? '' }}</td><td class=colhead>{{ $lang_invite['text_hash'] ?? '' }}</td><td class=colhead>{{ $lang_invite['text_send_date'] ?? '' }}</td>
            @if ($menuSelected == 'sent')
                <td class='colhead'>{{ $lang_invite['text_hash_status'] ?? '' }}</td>
            @endif
            <td class='colhead'>{{ $lang_invite['text_invitee_user'] ?? '' }}</td>
            @if ($menuSelected == 'tmp')
                <td class='colhead'>{{ $lang_invite['text_expired_at'] ?? '' }}</td>
                <td class='colhead'>{{ \App\Support\Locale::trans('label.created_at', [], null) }}</td>
            @endif
            </tr>
            @foreach ($sentTmpRows as $arr1)
                @php
                    $isHashValid = $arr1['valid'] == \App\Models\Invite::VALID_YES;
                    $registerLink = '';
                    if ($isHashValid) {
                        $registerLink = sprintf('&nbsp;<a href="signup.php?type=invite&invitenumber=%s" title="%s" target="_blank"><small>[%s]</small></a>', $arr1['hash'], $lang_invite['signup_link_help'] ?? '', $lang_invite['signup_link'] ?? '');
                    }
                @endphp
                <tr>
                <td class=rowfollow>{{ $arr1['invitee'] }}</td>
                <td class="rowfollow">{{ $arr1['hash'] }}{!! $registerLink !!}</td>
                <td class=rowfollow>{{ $arr1['time_invited'] }}</td>
                @if ($menuSelected == 'sent')
                    <td class=rowfollow>{{ \App\Models\Invite::$validInfo[$arr1['valid']]['text'] ?? '' }}</td>
                @endif
                @if (! $isHashValid)
                    <td class=rowfollow><a href=userdetails.php?id={{ $arr1['invitee_register_uid'] }}><font color=#1f7309>{{ $arr1['invitee_register_username'] }}</font></a></td>
                @else
                    <td class='rowfollow'></td>
                @endif
                @if ($menuSelected == 'tmp')
                    <td class=rowfollow>{{ $arr1['expired_at'] }}</td>
                    <td class=rowfollow>{{ $arr1['created_at'] }}</td>
                @endif
                </tr>
            @endforeach
        @endif
        </table>
        </td></tr></table>{{ $sentTmpPagertop }}
    @endif

@endif
@endsection
