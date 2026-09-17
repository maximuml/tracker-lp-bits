@extends('layouts.legacy')

@section('title', __('legacy/invite.head_invites'))

@section('content')
<div class="nx-main nx-embedded">

<h1 align=center><a href="invite.php?id={{ $id }}">{{ $user['username'] ?? '' }}{{ __('legacy/invite.text_invite_system')}}</a></h1>
@if ($sent == 1)
    <p align=center><font color=red>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/invite.text_invite_code_sent')))</font></p>
@endif

@if ($type == 'new')
    <form method=post action=takeinvite.php?id={{ (string) $id }}>
    <div class="nx-fgrid">
    <div class="nx-ffull nx-center"><b>{{ __('legacy/invite.text_invite_someone')}}{{ $SITENAME }} ({{ $inv['invites'] ?? 0 }}{{ __('legacy/invite.text_invitation')}}{{ $_s }}{{ __('legacy/invite.text_left')}} + {{ sprintf(__('legacy/invite.text_temporary_left'), count($temporaryInvites)) }})</b></div>
    <div class="nx-fhead nx-nowrap">{{ __('legacy/invite.text_email_address')}}</div><div class="nx-fcell"><input type=text size=40 name=email><br /><font align=left class=small>{{ __('legacy/invite.text_email_address_note') }}</font></div>
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($preUsernameTr))
    <div class="nx-fhead nx-nowrap">{{ __('legacy/invite.text_consume_invite')}}</div><div class="nx-fcell"><select name='hash'>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($inviteSelectOptions))</select></div>
    <div class="nx-fhead nx-nowrap">{{ __('legacy/invite.text_message')}}</div><div class="nx-fcell"><textarea name=body rows=10 style='width: 100%'>{{ $invitation_body }}</textarea></div>
    <div class="nx-ffull nx-center"><input type=submit value='{{ __('legacy/invite.submit_invite')}}'></div>
    </form></div></div>

@else
    {{-- Invite menu nav --}}
    <div id="invitenav" style='position: relative'><ul id="invitemenu" class="menu">
    <li{{ $menuSelected == 'invitee' ? ' class=selected' : '' }}><a href="?id={{ $id }}&menu=invitee">{{ __('legacy/invite.text_invite_status')}}</a></li>
    <li{{ $menuSelected == 'sent' ? ' class=selected' : '' }}><a href="?id={{ $id }}&menu=sent">{{ __('legacy/invite.text_sent_invites_status')}}</a></li>
    <li{{ $menuSelected == 'tmp' ? ' class=selected' : '' }}><a href="?id={{ $id }}&menu=tmp">{{ __('legacy/invite.text_tmp_status')}}</a></li>
    @if (($CURUSER['id'] ?? 0) == $id)
        </ul><form style='position: absolute;top:0;right:0' method=post action=invite.php?id={{ (string) $id }}&type=new><input type=submit{{ $sendBtnDisabled }} value='{{ $sendBtnText }}'></form></div>
    @else
        </ul></div>
    @endif

    @if ($menuSelected == 'invitee')
        <div>
            <form id="filterForm" action="{{ $__server_REQUEST_URI }}" method="get">
                <input type="hidden" name="menu" value="invitee" />
                <input type="hidden" name="id" value="{{ $id }}" />
                <span>{{ __('legacy/invite.text_enabled')}}:</span>
                <select name="enabled">
                    <option value="">-{{ $textSelectOnePlease }}-</option>
                    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($inviteeEnabledOptions))
                </select>
                &nbsp;&nbsp;
                <span>{{ __('legacy/invite.text_status')}}:</span>
                <select name="status">
                    <option value="">-{{ $textSelectOnePlease }}-</option>
                    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($inviteeStatusOptions))
                </select>
                &nbsp;&nbsp;
                <input type="submit" value="{{ $submitText }}">
                <input type="button" id="reset" value="{{ $resetText }}">
            </form>
        </div>
        <table data-nx="data" border=1 width=100% cellspacing=0 cellpadding=5>
        <form method=post action=takeconfirm.php?id={{ (string) $id }}>

        @if (! $inviteeCount)
            <tr><td colspan=7 align=center>{{ __('legacy/invite.text_no_invites')}}</tr>
        @else
            <tr>
            <td class=colhead><b>{{ __('legacy/invite.text_username')}}</b></td>
            <td class=colhead><b>{{ __('legacy/invite.text_email')}}</b></td>
            <td class=colhead><b>{{ __('legacy/invite.text_enabled')}}</b></td>
            <td class=colhead><b>{{ __('legacy/invite.text_uploaded_count')}}</b></td>
            <td class=colhead><b>{{ __('legacy/invite.text_uploaded')}}</b></td>
            <td class=colhead><b>{{ __('legacy/invite.text_downloaded')}}</b></td>
            <td class=colhead><b>{{ __('legacy/invite.text_ratio')}}</b></td>
            <td class=colhead><b>{{ __('legacy/invite.text_seed_torrent_count')}}</b></td>
            <td class=colhead><b>{{ __('legacy/invite.text_seed_torrent_size')}}</b></td>
            <td class=colhead title="{{ __('legacy/invite.text_seed_torrent_bonus_per_hour_help')}}"><b>{{ __('legacy/invite.text_seed_torrent_bonus_per_hour')}}</b></td>
            @if ($haremAdditionFactor > 0)
                <td class="colhead">{{ __('legacy/invite.harem_addition')}}</td>
            @endif
            <td class=colhead><b>{{ __('legacy/invite.text_seed_torrent_last_announce_at')}}</b></td>
            <td class=colhead><b>{{ __('legacy/invite.text_status')}}</b></td>
            @if ($canConfirm)
                <td class=colhead><b>{{ __('legacy/invite.text_confirm')}}</b></td>
            @endif
            </tr>
            @foreach ($inviteeRows as $arr)
                <tr class=rowfollow>
                    <td class=rowfollow>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['usernameHtml'] ?? ''))</td>
                    <td class=rowfollow>{{ $arr['email'] }}</td>
                    <td class=rowfollow>{{ $arr['enabled'] }}</td>
                    <td class=rowfollow>{{ $arr['torrent_count'] }}</td>
                    <td class=rowfollow>{{ \App\Support\Format::size($arr['uploaded']) }}</td>
                    <td class=rowfollow>{{ \App\Support\Format::size($arr['downloaded']) }}</td>
                    <td class=rowfollow>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['ratioHtml'] ?? ''))</td>
                    <td class=rowfollow>{{ number_format($arr['seeding_torrent_count']) }}</td>
                    <td class=rowfollow>{{ \App\Support\Format::size($arr['seeding_torrent_size']) }}</td>
                    <td class=rowfollow>{{ number_format($arr['seed_points_per_hour'], 3) }}</td>
                @if ($haremAdditionFactor > 0)
                    <td class=rowfollow>{{ number_format(floatval($arr['seed_points_per_hour']) * $haremAdditionFactor, 3) }}</td>
                @endif
                    <td class=rowfollow>{{ $arr['last_announce_at'] }}</td>
                    <td class=rowfollow>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr['statusHtml'] ?? ''))</td>
                    @if ($canConfirm)
                        <td class=rowfollow>
                        @if ($arr['status'] == 'pending')
                            <input type="checkbox" name="conusr[]" value="{{ $arr['id'] }}" />
                        @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        @endif

        @if ($canConfirm)
            @if ($pendingCount)
                <tr><td colspan={{ $inviteeColSpan }} align=right><input type=submit style='height: 20px' value='{{ __('legacy/invite.submit_confirm_users')}}'></td></tr>
            @endif
            </form>
        @endif
        </table>
        </div>{{ $inviteePagertop }}

    @elseif (in_array($menuSelected, ['sent', 'tmp'], true))
        <table data-nx="data" border=1 width=100% cellspacing=0 cellpadding=5>
        @if (! $sentTmpCount)
            <tr align=center><td colspan=6>{{ __('legacy/functions.text_none')}}</tr>
        @else
            <tr><td class=colhead>{{ __('legacy/invite.text_email')}}</td><td class=colhead>{{ __('legacy/invite.text_hash')}}</td><td class=colhead>{{ __('legacy/invite.text_send_date')}}</td>
            @if ($menuSelected == 'sent')
                <td class='colhead'>{{ __('legacy/invite.text_hash_status')}}</td>
            @endif
            <td class='colhead'>{{ __('legacy/invite.text_invitee_user')}}</td>
            @if ($menuSelected == 'tmp')
                <td class='colhead'>{{ __('legacy/invite.text_expired_at')}}</td>
                <td class='colhead'>{{ \App\Support\Locale::trans('label.created_at', [], null) }}</td>
            @endif
            </tr>
            @foreach ($sentTmpRows as $arr1)
                <tr>
                <td class=rowfollow>{{ $arr1['invitee'] }}</td>
                <td class="rowfollow">{{ $arr1['hash'] }}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr1['registerLink'] ?? ''))</td>
                <td class=rowfollow>{{ $arr1['time_invited'] }}</td>
                @if ($menuSelected == 'sent')
                    <td class=rowfollow>{{ $arr1['validText'] ?? '' }}</td>
                @endif
                <td class=rowfollow>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($arr1['inviteeUserHtml'] ?? ''))</td>
                @if ($menuSelected == 'tmp')
                    <td class=rowfollow>{{ $arr1['expired_at'] }}</td>
                    <td class=rowfollow>{{ $arr1['created_at'] }}</td>
                @endif
                </tr>
            @endforeach
        @endif
        </table>
        </div>{{ $sentTmpPagertop }}
    @endif

@endif
@endsection
