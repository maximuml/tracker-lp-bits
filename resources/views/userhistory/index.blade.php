@extends('layouts.legacy')

@section('title', $title ?? 'User history')

@section('content')
@if ($action === 'viewposts')
    <h1>{{ $lang_userhistory['text_posts_history_for'] ?? 'Posts history for ' }}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($subject))</h1>
    @if (($postcount ?? 0) > ($perpage ?? 15))
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagertop ?? ''))
    @endif
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open('', false, 10, '100%', 'left')))
    @foreach ($items ?? [] as $item)
        <p class=sub><table border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['added']))&nbsp;--&nbsp;@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_userhistory['text_forum'] ?? ''))
        <a href=forums.php?action=viewforum&forumid={{ $item['forumid'] }}>{{ $item['forumname'] }}</a>
        &nbsp;--&nbsp;@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_userhistory['text_topic'] ?? ''))
        <a href=forums.php?action=viewtopic&topicid={{ $item['topicid'] }}>{{ $item['topicname'] }}</a>
        &nbsp;--&nbsp;@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_userhistory['text_post'] ?? ''))
        <a href=forums.php?action=viewtopic&topicid={{ $item['topicid'] }}&page=p{{ $item['postid'] }}#pid{{ $item['postid'] }}>#{{ $item['postid'] }}</a>
        @if ($item['isNew']) &nbsp;<b>(<font class=new>{{ $lang_userhistory['text_new'] ?? 'New' }}</font>)</b>@endif
        </td></tr></table></p>
        <br />
        <table class=main width=100% border=1 cellspacing=0 cellpadding=5>
        <tr valign=top><td class=comment>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['bodyHtml']))</td></tr>
        </table>
        <br />
    @endforeach
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))
    @if (($postcount ?? 0) > ($perpage ?? 15))
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
    @endif
@elseif ($action === 'viewcomments')
    <h1>{{ $lang_userhistory['text_comments_history_for'] ?? 'Comments history for ' }}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($subject))</h1>
    @if (($commentcount ?? 0) > ($perpage ?? 15))
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagertop ?? ''))
    @endif
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::open('', false, 10, '100%', 'left')))
    @foreach ($items ?? [] as $item)
        <p class=sub><table border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['added']))&nbsp;---&nbsp;@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_userhistory['text_torrent'] ?? ''))
        @if ($item['torrentName'] !== '')
            <a href=details.php?id={{ $item['torrentid'] }}&tocomm=1&hit=1>{{ $item['torrentName'] }}</a>
        @else
            [Deleted]
        @endif
        &nbsp;---&nbsp;@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_userhistory['text_comment'] ?? ''))</b>#<a href=details.php?id={{ $item['torrentid'] }}&tocomm=1&hit=1{{ $item['pageUrl'] }}>{{ $item['commentid'] }}</a>
        </td></tr></table></p>
        <br />
        <table class=main width=100% border=1 cellspacing=0 cellpadding=5>
        <tr valign=top><td class=comment>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($item['bodyHtml']))</td></tr>
        </table>
        <br />
    @endforeach
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))
    @if (($commentcount ?? 0) > ($perpage ?? 15))
        @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
    @endif
@endif
@endsection
