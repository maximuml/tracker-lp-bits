@extends('layouts.app')

@section('title', __('invite.head_invites'))

@section('content')
<x-page :title="$inviteTitle" :width="$type === 'new' ? 'form' : 'list'">
@if ($type !== 'new')
    @if (($CURUSER['id'] ?? 0) == $id)
        <x-slot:actions><form method="post" action="/web/invites/submit?id={{ (string) $id }}&type=new">@csrf<input type="submit" class="nx-btn nx-btn--primary"{{ $sendBtnDisabled }} value='{{ $sendBtnText }}' data-submit-once></form></x-slot:actions>
    @endif
    <x-slot:tabs><x-tabs :tabs="$inviteTabs" :active="$menuSelected" :label="__('invite.head_invites')" /></x-slot:tabs>
@endif
@if ($sent == 1)
    <p class="text-center"><span class="text-nxm-danger">{{ __('invite.text_invite_code_sent') }}<br /></span></p>
@endif

@if ($type == 'new')
    <form method="post" action="/web/invites/send?id={{ (string) $id }}">@csrf
    <div class="nx-fgrid">
    <div class="nx-ffull text-center"><b>{{ __('invite.text_invite_someone')}}{{ $SITENAME }} ({{ $inv['invites'] ?? 0 }}{{ __('invite.text_invitation')}}{{ $_s }}{{ __('invite.text_left')}} + {{ sprintf(__('invite.text_temporary_left'), count($temporaryInvites)) }})</b></div>
    <div class="nx-fhead whitespace-nowrap">{{ __('invite.text_email_address')}}</div><div class="nx-fcell"><input type="email" size="40" name="email" autocomplete="email"><br /><span class="small">{{ __('invite.text_email_address_note') }}</span></div>
    @if ($showPreUsername)
    <div class="nx-fhead whitespace-nowrap">{{ $preUsernameLabel }}</div><div class="nx-fcell"><input type="text" size="40" name="pre_register_username" autocomplete="off"><br /><span class="small">{{ $preUsernameHelp }}</span></div>
    @endif
    <div class="nx-fhead whitespace-nowrap">{{ __('invite.text_consume_invite')}}</div><div class="nx-fcell"><select name='hash' aria-label="{{ __('invite.text_consume_invite') }}">@foreach ($inviteOptions as $opt)<option value="{{ $opt['value'] }}">{{ $opt['text'] }}</option>@endforeach</select></div>
    <div class="nx-fhead whitespace-nowrap">{{ __('invite.text_message')}}</div><div class="nx-fcell"><textarea name="body" rows="10">{{ $invitation_body }}</textarea></div>
    <div class="nx-ffull text-center"><input type="submit" class="nx-btn nx-btn--primary" value='{{ __('invite.submit_invite')}}' data-submit-once></div>
    </form></div>

@else
    @if ($menuSelected == 'invitee')
        <div>
            <form id="filterForm" action="{{ $__server_REQUEST_URI }}" method="get">
                <input type="hidden" name="menu" value="invitee" />
                <input type="hidden" name="id" value="{{ $id }}" />
                <span>{{ __('invite.text_enabled')}}:</span>
                <select name="enabled" aria-label="Enabled">
                    <option value="">-{{ $textSelectOnePlease }}-</option>
                    @foreach ($inviteeEnabledOptions as $opt)<option value="{{ $opt['value'] }}"{{ $opt['selected'] ? ' selected' : '' }}>{{ $opt['text'] }}</option>@endforeach
                </select>
                &nbsp;&nbsp;
                <span>{{ __('invite.text_status')}}:</span>
                <select name="status" aria-label="Status">
                    <option value="">-{{ $textSelectOnePlease }}-</option>
                    @foreach ($inviteeStatusOptions as $opt)<option value="{{ $opt['value'] }}"{{ $opt['selected'] ? ' selected' : '' }}>{{ $opt['text'] }}</option>@endforeach
                </select>
                &nbsp;&nbsp;
                <input type="submit" class="nx-btn nx-btn--primary" value="{{ $submitText }}" data-submit-once>
                <input type="button" id="reset" class="nx-btn" value="{{ $resetText }}">
            </form>
        </div>
        @if (! $inviteeCount)
            <x-empty-state :title="__('invite.text_no_invites')" />
        @else
        <x-data-table :caption="__('invite.text_invite_status')" captionHidden>
        <form method="post" action="/takeconfirm?id={{ (string) $id }}">@csrf
            <tr>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('invite.text_username')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('invite.text_email')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('invite.text_enabled')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('invite.text_uploaded_count')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('invite.text_uploaded')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('invite.text_downloaded')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('invite.text_ratio')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('invite.text_seed_torrent_count')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('invite.text_seed_torrent_size')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" title="{{ __('invite.text_seed_torrent_bonus_per_hour_help')}}" scope="col"><b>{{ __('invite.text_seed_torrent_bonus_per_hour')}}</b></th>
            @if ($haremAdditionFactor > 0)
                <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('invite.harem_addition')}}</th>
            @endif
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('invite.text_seed_torrent_last_announce_at')}}</b></th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('invite.text_status')}}</b></th>
            @if ($canConfirm)
                <th class="bg-nxm-surface-alt font-semibold" scope="col"><b>{{ __('invite.text_confirm')}}</b></th>
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
                    <td class="align-top px-2.5 py-1.5">@if (($arr['status'] ?? '') === 'confirmed')<a href="/userdetails?id={{ (int) $arr['id'] }}"><span class="nx-color-1f7309">{{ __('invite.text_confirmed') }}</span></a>@else<a href="/checkuser?id={{ (int) $arr['id'] }}"><span class="nx-color-ca0226">{{ __('invite.text_pending') }}</span></a>@endif</td>
                    @if ($canConfirm)
                        <td class="align-top px-2.5 py-1.5">
                        @if ($arr['status'] == 'pending')
                            <input type="checkbox" name="conusr[]" value="{{ $arr['id'] }}" />
                        @endif
                        </td>
                    @endif
                </tr>
            @endforeach

            @if ($canConfirm)
                @if ($pendingCount)
                    <tr><td colspan="{{ $inviteeColSpan }}" class="text-right"><input type="submit" class="nx-btn nx-btn--primary" value='{{ __('invite.submit_confirm_users')}}' data-submit-once></td></tr>
                @endif
                </form>
            @endif
        </x-data-table>
        @endif
        {{ $inviteePagertop }}

    @elseif (in_array($menuSelected, ['sent', 'tmp'], true))
        @if (! $sentTmpCount)
            <x-empty-state :title="__('functions.text_none')" />
        @else
        <x-data-table :caption="$menuSelected == 'sent' ? __('invite.text_sent_invites_status') : __('invite.text_tmp_status')" captionHidden>
            <tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('invite.text_email')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('invite.text_hash')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('invite.text_send_date')}}</th>
            @if ($menuSelected == 'sent')
                <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('invite.text_hash_status')}}</th>
            @endif
            <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('invite.text_invitee_user')}}</th>
            @if ($menuSelected == 'tmp')
                <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('invite.text_expired_at')}}</th>
                <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ \App\Support\Locale::trans('label.created_at', [], null) }}</th>
            @endif
            </tr>
            @foreach ($sentTmpRows as $arr1)
                <tr>
                <td class="align-top px-2.5 py-1.5">{{ $arr1['invitee'] }}</td>
                <td class="align-top px-2.5 py-1.5">{{ $arr1['hash'] }}@if ($arr1['hashValid'] ?? false)&nbsp;<a href="/signup?type=invite&invitenumber={{ $arr1['hash'] }}" title="{{ __('invite.signup_link_help') }}" target="_blank" rel="noopener"><small>[{{ __('invite.signup_link') }}]</small></a>@endif</td>
                <td class="align-top px-2.5 py-1.5">{{ $arr1['time_invited'] }}</td>
                @if ($menuSelected == 'sent')
                    <td class="align-top px-2.5 py-1.5">{{ $arr1['validText'] ?? '' }}</td>
                @endif
                <td class="align-top px-2.5 py-1.5">@if (! ($arr1['hashValid'] ?? false))<a href="/userdetails?id={{ (int) $arr1['invitee_register_uid'] }}"><span class="nx-color-1f7309">{{ $arr1['invitee_register_username'] }}</span></a>@endif</td>
                @if ($menuSelected == 'tmp')
                    <td class="align-top px-2.5 py-1.5">{{ $arr1['expired_at'] }}</td>
                    <td class="align-top px-2.5 py-1.5">{{ $arr1['created_at'] }}</td>
                @endif
                </tr>
            @endforeach
        </x-data-table>
        @endif
        {{ $sentTmpPagertop }}
    @endif
@endif
</x-page>
@endsection
