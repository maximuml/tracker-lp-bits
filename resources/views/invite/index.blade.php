@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('legacy/invite.head_invites'))

@section('content')
<div class="nx-main nx-embedded">

<h1 class="text-center"><a href="invite.php?id={{ $id }}">{{ $user['username'] ?? '' }}{{ __('legacy/invite.text_invite_system')}}</a></h1>
@if ($sent == 1)
    <p class="text-center"><span class="text-nxm-danger">{{ __('legacy/invite.text_invite_code_sent') }}<br /></span></p>
@endif

@if ($type == 'new')
    <form method=post action=takeinvite.php?id={{ (string) $id }}>
    <div class="nx-fgrid">
    <div class="nx-ffull text-center"><b>{{ __('legacy/invite.text_invite_someone')}}{{ $SITENAME }} ({{ $inv['invites'] ?? 0 }}{{ __('legacy/invite.text_invitation')}}{{ $_s }}{{ __('legacy/invite.text_left')}} + {{ sprintf(__('legacy/invite.text_temporary_left'), count($temporaryInvites)) }})</b></div>
    <div class="nx-fhead whitespace-nowrap">{{ __('legacy/invite.text_email_address')}}</div><div class="nx-fcell"><input type=text size=40 name=email><br /><span class="small">{{ __('legacy/invite.text_email_address_note') }}</span></div>
    @if ($showPreUsername)
    <div class="nx-fhead whitespace-nowrap">{{ $preUsernameLabel }}</div><div class="nx-fcell"><input type=text size=40 name=pre_register_username><br /><span class="small">{{ $preUsernameHelp }}</span></div>
    @endif
    <div class="nx-fhead whitespace-nowrap">{{ __('legacy/invite.text_consume_invite')}}</div><div class="nx-fcell"><select name='hash'>@foreach ($inviteOptions as $opt)<option value="{{ $opt['value'] }}">{{ $opt['text'] }}</option>@endforeach</select></div>
    <div class="nx-fhead whitespace-nowrap">{{ __('legacy/invite.text_message')}}</div><div class="nx-fcell"><textarea name=body rows=10>{{ $invitation_body }}</textarea></div>
    <div class="nx-ffull text-center"><input type=submit value='{{ __('legacy/invite.submit_invite')}}'></div>
    </form></div></div>

@else
    {{-- Invite menu nav --}}
    <div id="invitenav"><ul id="invitemenu" class="menu">
    <li{{ $menuSelected == 'invitee' ? ' class=selected' : '' }}><a href="?id={{ $id }}&menu=invitee">{{ __('legacy/invite.text_invite_status')}}</a></li>
    <li{{ $menuSelected == 'sent' ? ' class=selected' : '' }}><a href="?id={{ $id }}&menu=sent">{{ __('legacy/invite.text_sent_invites_status')}}</a></li>
    <li{{ $menuSelected == 'tmp' ? ' class=selected' : '' }}><a href="?id={{ $id }}&menu=tmp">{{ __('legacy/invite.text_tmp_status')}}</a></li>
    @if (($CURUSER['id'] ?? 0) == $id)
        </ul><form method=post action=invite.php?id={{ (string) $id }}&type=new><input type=submit{{ $sendBtnDisabled }} value='{{ $sendBtnText }}'></form></div>
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
                    @foreach ($inviteeEnabledOptions as $opt)<option value="{{ $opt['value'] }}"{{ $opt['selected'] ? ' selected' : '' }}>{{ $opt['text'] }}</option>@endforeach
                </select>
                &nbsp;&nbsp;
                <span>{{ __('legacy/invite.text_status')}}:</span>
                <select name="status">
                    <option value="">-{{ $textSelectOnePlease }}-</option>
                    @foreach ($inviteeStatusOptions as $opt)<option value="{{ $opt['value'] }}"{{ $opt['selected'] ? ' selected' : '' }}>{{ $opt['text'] }}</option>@endforeach
                </select>
                &nbsp;&nbsp;
                <input type="submit" value="{{ $submitText }}">
                <input type="button" id="reset" value="{{ $resetText }}">
            </form>
        </div>
        <table data-nx="data"><caption class="nx-sr-only">{{ __('legacy/invite.text_invite_status') }}</caption>
        <form method=post action=takeconfirm.php?id={{ (string) $id }}>

        @if (! $inviteeCount)
            <tr><td colspan=7 class="text-center">{{ __('legacy/invite.text_no_invites')}}</tr>
        @else
            <tr>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('legacy/invite.text_username')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('legacy/invite.text_email')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('legacy/invite.text_enabled')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('legacy/invite.text_uploaded_count')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('legacy/invite.text_uploaded')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('legacy/invite.text_downloaded')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('legacy/invite.text_ratio')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('legacy/invite.text_seed_torrent_count')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('legacy/invite.text_seed_torrent_size')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" title="{{ __('legacy/invite.text_seed_torrent_bonus_per_hour_help')}}" scope="col"><b>{{ __('legacy/invite.text_seed_torrent_bonus_per_hour')}}</b></th>
            @if ($haremAdditionFactor > 0)
                <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/invite.harem_addition')}}</th>
            @endif
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('legacy/invite.text_seed_torrent_last_announce_at')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('legacy/invite.text_status')}}</b></th>
            @if ($canConfirm)
                <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('legacy/invite.text_confirm')}}</b></th>
            @endif
            </tr>
            @foreach ($inviteeRows as $arr)
                <tr class="align-top px-2.5 py-1.5">
                    <td class="align-top px-2.5 py-1.5">{{ $arr['usernameHtml'] ?? '' }}</td>
                    <td class="align-top px-2.5 py-1.5">{{ $arr['email'] }}</td>
                    <td class="align-top px-2.5 py-1.5">{{ $arr['enabled'] }}</td>
                    <td class="align-top px-2.5 py-1.5">{{ $arr['torrent_count'] }}</td>
                    <td class="align-top px-2.5 py-1.5">{{ \App\Support\Format::size($arr['uploaded']) }}</td>
                    <td class="align-top px-2.5 py-1.5">{{ \App\Support\Format::size($arr['downloaded']) }}</td>
                    <td class="align-top px-2.5 py-1.5">@if (($arr['ratioClass'] ?? '') !== '')<span class="{{ $arr['ratioClass'] }}">{{ $arr['ratioText'] }}</span>@else{{ $arr['ratioText'] ?? '' }}@endif</td>
                    <td class="align-top px-2.5 py-1.5">{{ number_format($arr['seeding_torrent_count']) }}</td>
                    <td class="align-top px-2.5 py-1.5">{{ \App\Support\Format::size($arr['seeding_torrent_size']) }}</td>
                    <td class="align-top px-2.5 py-1.5">{{ number_format($arr['seed_points_per_hour'], 3) }}</td>
                @if ($haremAdditionFactor > 0)
                    <td class="align-top px-2.5 py-1.5">{{ number_format(floatval($arr['seed_points_per_hour']) * $haremAdditionFactor, 3) }}</td>
                @endif
                    <td class="align-top px-2.5 py-1.5">{{ $arr['last_announce_at'] }}</td>
                    <td class="align-top px-2.5 py-1.5">@if (($arr['status'] ?? '') === 'confirmed')<a href=userdetails.php?id={{ (int) $arr['id'] }}><span class="nx-color-1f7309">{{ __('legacy/invite.text_confirmed') }}</span></a>@else<a href=checkuser.php?id={{ (int) $arr['id'] }}><span class="nx-color-ca0226">{{ __('legacy/invite.text_pending') }}</span></a>@endif</td>
                    @if ($canConfirm)
                        <td class="align-top px-2.5 py-1.5">
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
                <tr><td colspan={{ $inviteeColSpan }} class="text-right"><input type=submit value='{{ __('legacy/invite.submit_confirm_users')}}'></td></tr>
            @endif
            </form>
        @endif
        </table>
        </div>{{ $inviteePagertop }}

    @elseif (in_array($menuSelected, ['sent', 'tmp'], true))
        <table data-nx="data"><caption class="nx-sr-only">{{ $menuSelected == 'sent' ? __('legacy/invite.text_sent_invites_status') : __('legacy/invite.text_tmp_status') }}</caption>
        @if (! $sentTmpCount)
            <tr class="text-center"><td colspan=6>{{ __('legacy/functions.text_none')}}</tr>
        @else
            <tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/invite.text_email')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/invite.text_hash')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/invite.text_send_date')}}</th>
            @if ($menuSelected == 'sent')
                <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/invite.text_hash_status')}}</th>
            @endif
            <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/invite.text_invitee_user')}}</th>
            @if ($menuSelected == 'tmp')
                <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/invite.text_expired_at')}}</th>
                <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ \App\Support\Locale::trans('label.created_at', [], null) }}</th>
            @endif
            </tr>
            @foreach ($sentTmpRows as $arr1)
                <tr>
                <td class="align-top px-2.5 py-1.5">{{ $arr1['invitee'] }}</td>
                <td class="align-top px-2.5 py-1.5">{{ $arr1['hash'] }}@if ($arr1['hashValid'] ?? false)&nbsp;<a href="signup.php?type=invite&invitenumber={{ $arr1['hash'] }}" title="{{ __('legacy/invite.signup_link_help') }}" target="_blank"><small>[{{ __('legacy/invite.signup_link') }}]</small></a>@endif</td>
                <td class="align-top px-2.5 py-1.5">{{ $arr1['time_invited'] }}</td>
                @if ($menuSelected == 'sent')
                    <td class="align-top px-2.5 py-1.5">{{ $arr1['validText'] ?? '' }}</td>
                @endif
                <td class="align-top px-2.5 py-1.5">@if (! ($arr1['hashValid'] ?? false))<a href=userdetails.php?id={{ (int) $arr1['invitee_register_uid'] }}><span class="nx-color-1f7309">{{ $arr1['invitee_register_username'] }}</span></a>@endif</td>
                @if ($menuSelected == 'tmp')
                    <td class="align-top px-2.5 py-1.5">{{ $arr1['expired_at'] }}</td>
                    <td class="align-top px-2.5 py-1.5">{{ $arr1['created_at'] }}</td>
                @endif
                </tr>
            @endforeach
        @endif
        </table>
        </div>{{ $sentTmpPagertop }}
    @endif

@endif
@endsection
