@extends('layouts.legacy')

@section('title', $title)

@section('content')
<p><table class=main border=0 cellspacing=0 cellpadding=0>
<tr><td class=embedded><h1 style='margin:0px'> {{ $lang_friends['text_personallist'] ?? 'Personal list for' }} @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($titleUsername))</h1></td></tr></table></p>

<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
<br />
<h2 align=left><a name="friends">{{ $lang_friends['text_friendlist'] ?? 'Friend list' }}</a></h2>
<table width=737 border=1 cellspacing=0 cellpadding=5><tr class=tablea><td>

@if (empty($friendsList))
    {{ $lang_friends['text_friends_empty'] ?? 'No friends.' }}
@else
    @foreach ($friendsList as $friend)
        @if ($loop->index % 2 == 0)
            <table width=100% style='padding: 0px'><tr><td class=bottom style='padding: 5px' width=50% align=center>
        @else
            <td class=bottom style='padding: 5px' width=50% align=center class=tablea>
        @endif
        <table class=main width=100% height=75px class=tablea>
        <tr valign=top class=tableb><td width=75 align=center style='padding: 0px'>
        <div style='width:75px;height:75px;overflow: hidden'><img width=75px src="{{ $friend['avatarSrc'] }}"></div>
        </td><td>
        <table class=main>
        <tr><td class=embedded style='padding: 5px' width=80%>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($friend['body1Html'] ?? ''))</td>
        <td class=embedded style='padding: 5px' width=20%>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($friend['body2Html'] ?? ''))</td></tr>
        </table>
        </td></tr>
        </td></tr></table>
        @if ($loop->index % 2 == 1)
            </td></tr></table>
        @else
            </td>
        @endif
    @endforeach
@endif
@if (count($friendsList) % 2 == 1)
    <td class=bottom width=50%>&nbsp;</td></tr></table>
@endif

</td></tr></table><br />

<br /><br />
<table class=main width=737 border=0 cellspacing=0 cellpadding=5><tr><td class=embedded>
<h2 align=left><a name="blocks">{{ $lang_friends['text_blocked_users'] ?? 'Blocked users' }}</a></h2></td></tr>
<tr class=tableb><td style='padding: 10px;'>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($blocksHtml))
</td></tr></table>

</td></tr></table>
@if ($canViewUserList)
    <p><a href=users.php><b>{{ $lang_friends['text_find_user'] ?? 'Find user' }}</b></a></p>
@endif
@endsection
