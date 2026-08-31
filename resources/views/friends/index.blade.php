@php
$lang_friends = (array) (\app(\App\Support\Globals::class)->get('lang_friends') ?? []);
$CURUSER = (array) (\app(\App\Support\CurrentUser::class)->get() ?? []);
$userid = (int) ($userid ?? 0);
$friendsList = (array) ($friendsList ?? []);
$blockRows = (array) ($blockRows ?? []);
$userDisplayMap = (array) ($userDisplayMap ?? []);
$titleUsername = (string) ($titleUsername ?? '');
$canViewUserList = (bool) ($canViewUserList ?? false);
$title = $title ?? (($lang_friends['head_personal_lists_for'] ?? 'Personal lists for ') . ($titleUsername ?: ($CURUSER['username'] ?? '')));
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
<p><table class=main border=0 cellspacing=0 cellpadding=0>
<tr><td class=embedded><h1 style='margin:0px'> {{ $lang_friends['text_personallist'] ?? 'Personal list for' }} {{ $titleUsername }}</h1></td></tr></table></p>

<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
<br />
<h2 align=left><a name="friends">{{ $lang_friends['text_friendlist'] ?? 'Friend list' }}</a></h2>
<table width=737 border=1 cellspacing=0 cellpadding=5><tr class=tablea><td>

@php $i = 0; @endphp
@if (empty($friendsList))
    {{ $lang_friends['text_friends_empty'] ?? 'No friends.' }}
@else
    @foreach ($friendsList as $friend)
        @php
            $friendId = (int) ($friend['id'] ?? 0);
            $friendTitle = htmlspecialchars((string) ($friend['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $lastAccess = (string) ($friend['last_access'] ?? '');
            $avatar = ($CURUSER['avatars'] ?? '') === 'yes' ? htmlspecialchars((string) ($friend['avatar'] ?? '')) : '';
            if (! $avatar) {
                $avatar = 'pic/default_avatar.png';
            }
            $usernameHtml = $userDisplayMap[$friendId] ?? \App\Support\UserDisplay::username($friendId);
            $body1 = $usernameHtml." ($friendTitle)<br /><br />".($lang_friends['text_last_seen_on'] ?? 'Last seen on ').\App\Support\Time::format($lastAccess, true, false);
            $body2 = "<a href=friends.php?id=$userid&action=delete&type=friend&targetid=$friendId>".htmlspecialchars($lang_friends['text_remove_from_friends'] ?? 'Remove from friends', ENT_QUOTES, 'UTF-8').'</a>'.
                "<br /><br /><a href=sendmessage.php?receiver=$friendId>".htmlspecialchars($lang_friends['text_send_pm'] ?? 'Send PM', ENT_QUOTES, 'UTF-8').'</a>';
        @endphp
        @if ($i % 2 == 0)
            <table width=100% style='padding: 0px'><tr><td class=bottom style='padding: 5px' width=50% align=center>
        @else
            <td class=bottom style='padding: 5px' width=50% align=center class=tablea>
        @endif
        <table class=main width=100% height=75px class=tablea>
        <tr valign=top class=tableb><td width=75 align=center style='padding: 0px'>
        @if ($avatar)<div style='width:75px;height:75px;overflow: hidden'><img width=75px src="{{ $avatar }}"></div>@endif
        </td><td>
        <table class=main>
        <tr><td class=embedded style='padding: 5px' width=80%>{!! $body1 !!}</td>
        <td class=embedded style='padding: 5px' width=20%>{!! $body2 !!}</td></tr>
        </table>
        </td></tr>
        </td></tr></table>
        @if ($i % 2 == 1)
            </td></tr></table>
        @else
            </td>
        @endif
        @php $i++; @endphp
    @endforeach
@endif
@if ($i % 2 == 1)
    <td class=bottom width=50%>&nbsp;</td></tr></table>
@endif

</td></tr></table><br />

@php
    $blocks = '';
    if (empty($blockRows)) {
        $blocks = $lang_friends['text_blocklist_empty'] ?? 'No blocked users.';
    } else {
        $i = 0;
        $blocks = '<table width=100% cellspacing=0 cellpadding=0>';
        foreach ($blockRows as $block) {
            $blockId = (int) ($block['id'] ?? 0);
            if ($i % 6 == 0) {
                $blocks .= '<tr>';
            }
            $blocks .= "<td style='border: none; padding: 4px; spacing: 0px;'>[<font class=small><a href=friends.php?id=$userid&action=delete&type=block&targetid=$blockId>D</a></font>] ".
                ($userDisplayMap[$blockId] ?? \App\Support\UserDisplay::username($blockId)).'</td>';
            if ($i % 6 == 5) {
                $blocks .= '</tr>';
            }
            $i++;
        }
        $blocks .= "</table>\n";
    }
@endphp

<br /><br />
<table class=main width=737 border=0 cellspacing=0 cellpadding=5><tr><td class=embedded>
<h2 align=left><a name="blocks">{{ $lang_friends['text_blocked_users'] ?? 'Blocked users' }}</a></h2></td></tr>
<tr class=tableb><td style='padding: 10px;'>
{!! $blocks !!}
</td></tr></table>

</td></tr></table>
@if ($canViewUserList)
    <p><a href=users.php><b>{{ $lang_friends['text_find_user'] ?? 'Find user' }}</b></a></p>
@endif
@endsection
