@extends('layouts.legacy')

@section('title', $title)

@section('content')
<p><div class="nx-main nx-embedded">
<h1 style='margin:0px'> {{ __('legacy/friends.text_personallist')}} @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($titleUsername))</h1></div></p>

<div class="nx-main nx-embedded nx-box--737">
<br />
<h2 align=left><a name="friends">{{ __('legacy/friends.text_friendlist')}}</a></h2>
<div class="nx-box nx-box--tight nx-box--737">

@if (empty($friendsList))
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/friends.text_friends_empty')))
@else
    <div class="nx-fcards">
    @foreach ($friendsList as $friend)
        <div>
        <div class="nx-fcard nx-main">
        <div class="nx-center" style='padding: 0px;width:75px'>
        <div style='width:75px;height:75px;overflow: hidden'><img width=75px src="{{ $friend['avatarSrc'] }}"></div>
        </div><div class="nx-grow">
        <div class="nx-row nx-main">
        <div class="nx-embedded nx-w-80" style='padding: 5px'>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($friend['body1Html'] ?? ''))</div>
        <div class="nx-embedded nx-w-20" style='padding: 5px'>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($friend['body2Html'] ?? ''))</div>
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
<h2 align=left><a name="blocks">{{ __('legacy/friends.text_blocked_users')}}</a></h2>
<div style='padding: 10px;'>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($blocksHtml))
</div>
</div>

</div>
@if ($canViewUserList)
    <p><a href=users.php><b>{{ __('legacy/friends.text_find_user')}}</b></a></p>
@endif
@endsection
