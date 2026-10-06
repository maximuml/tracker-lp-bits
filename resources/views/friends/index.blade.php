@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title)

@section('content')
<p><div class="nx-main nx-embedded">
<h1> {{ __('legacy/friends.text_personallist')}} {{ $titleUsername }}</h1></div></p>

<div class="nx-main nx-embedded nx-box--737">
<br />
<section class="nx-idx-card">
<h2><a name="friends">{{ __('legacy/friends.text_friendlist')}}</a></h2>
<div>

@if (empty($friendsList))
    <x-empty-state :title="__('legacy/friends.text_friends_empty')" />
@else
    <div class="nx-fcards">
    @foreach ($friendsList as $friend)
        <div>
        <div class="nx-fcard nx-main">
        <div class="text-center">
        <div><img width="75" src="{{ $friend['avatarSrc'] }}"></div>
        </div><div class="grow">
        <div class="flex items-start nx-main">
        <div class="nx-embedded w-[80%]">{{ $friend['usernameHtml'] }} ({{ $friend['titleHtml'] }})<br /><br />{{ __('legacy/friends.text_last_seen_on') }}<x-time :value="$friend['lastSeen']" /></div>
        <div class="nx-embedded w-[20%]"><form method="post" action="/web/friends/delete" class="nx-inline">@csrf<input type="hidden" name="id" value="{{ $userid }}" /><input type="hidden" name="type" value="friend" /><input type="hidden" name="targetid" value="{{ $friend['id'] }}" /><button type="submit" class="nxm-linkbtn">{{ __('legacy/friends.text_remove_from_friends') }}</button></form><br /><br /><a href="/web/sendmessage?receiver={{ $friend['id'] }}">{{ __('legacy/friends.text_send_pm') }}</a></div>
        </div>
        </div>
        </div>
        </div>
    @endforeach
    </div>
@endif

</div>
</section>

<section class="nx-idx-card">
<h2><a name="blocks">{{ __('legacy/friends.text_blocked_users')}}</a></h2>
<div>
@if ($blocks === [])
<x-empty-state :title="__('legacy/friends.text_blocklist_empty')" />
@else
<div class="nxm-grid-6">@foreach ($blocks as $block)<div>[<form method="post" action="/web/friends/delete" class="nx-inline">@csrf<input type="hidden" name="id" value="{{ $userid }}" /><input type="hidden" name="type" value="block" /><input type="hidden" name="targetid" value="{{ $block['id'] }}" /><button type="submit" class="nxm-linkbtn small">D</button></form>] {{ $block['usernameHtml'] }}</div>@endforeach</div>
@endif
</div>
</section>

</div>
@if ($canViewUserList)
    <p><a href=/web/users><b>{{ __('legacy/friends.text_find_user')}}</b></a></p>
@endif
@endsection
