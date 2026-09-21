@extends('layouts.legacy')

@section('title', $title)

@section('content')
<p><div class="nx-main nx-embedded">
<h1> {{ __('legacy/friends.text_personallist')}} {{ $titleUsername }}</h1></div></p>

<div class="nx-main nx-embedded nx-box--737">
<br />
<h2><a name="friends">{{ __('legacy/friends.text_friendlist')}}</a></h2>
<div class="nx-box nx-box--tight nx-box--737">

@if (empty($friendsList))
    <x-empty-state :title="__('legacy/friends.text_friends_empty')" />
@else
    <div class="nx-fcards">
    @foreach ($friendsList as $friend)
        <div>
        <div class="nx-fcard nx-main">
        <div class="nx-center">
        <div><img width="75" src="{{ $friend['avatarSrc'] }}"></div>
        </div><div class="nx-grow">
        <div class="nx-row nx-main">
        <div class="nx-embedded nx-w-80">{{ $friend['usernameHtml'] }} ({{ $friend['titleHtml'] }})<br /><br />{{ __('legacy/friends.text_last_seen_on') }}<x-time :value="$friend['lastSeen']" /></div>
        <div class="nx-embedded nx-w-20"><a href="friends.php?id={{ $userid }}&action=delete&type=friend&targetid={{ $friend['id'] }}">{{ __('legacy/friends.text_remove_from_friends') }}</a><br /><br /><a href="sendmessage.php?receiver={{ $friend['id'] }}">{{ __('legacy/friends.text_send_pm') }}</a></div>
        </div>
        </div>
        </div>
        </div>
    @endforeach
    </div>
@endif

</div><br />

<br /><br />
<div class="nx-main nx-embedded nx-box--737 nx-cell-5">
<h2><a name="blocks">{{ __('legacy/friends.text_blocked_users')}}</a></h2>
<div>
@if ($blocks === [])
<x-empty-state :title="__('legacy/friends.text_blocklist_empty')" />
@else
<div class="nxm-grid-6">@foreach ($blocks as $block)<div>[<span class='small'><a href="friends.php?id={{ $userid }}&action=delete&type=block&targetid={{ $block['id'] }}">D</a></span>] {{ $block['usernameHtml'] }}</div>@endforeach</div>
@endif
</div>
</div>

</div>
@if ($canViewUserList)
    <p><a href=users.php><b>{{ __('legacy/friends.text_find_user')}}</b></a></p>
@endif
@endsection
