@extends('layouts.legacy')

@section('title', $title ?? 'User history')

@section('content')
@if ($action === 'viewposts')
    <h1>{{ __('legacy/userhistory.text_posts_history_for')}}{{ $subject }}</h1>
    @if (($postcount ?? 0) > ($perpage ?? 15))
        {{ $pagertop ?? '' }}
    @endif
    {{ \App\Support\Frame::open('', false, 10, '100%', 'left') }}
    @foreach ($items ?? [] as $item)
        <p class=sub>
        {{ $item['added'] }}&nbsp;--&nbsp;<b>{{ __('legacy/userhistory.text_forum') }}&nbsp;</b>
        <a href=forums.php?action=viewforum&forumid={{ $item['forumid'] }}>{{ $item['forumname'] }}</a>
        &nbsp;--&nbsp;<b>{{ __('legacy/userhistory.text_topic') }}&nbsp;</b>
        <a href=forums.php?action=viewtopic&topicid={{ $item['topicid'] }}>{{ $item['topicname'] }}</a>
        &nbsp;--&nbsp;<b>{{ __('legacy/userhistory.text_post') }}&nbsp;</b>
        <a href=forums.php?action=viewtopic&topicid={{ $item['topicid'] }}&page=p{{ $item['postid'] }}#pid{{ $item['postid'] }}>#{{ $item['postid'] }}</a>
        @if ($item['isNew']) &nbsp;<b>(<font class=new>{{ __('legacy/userhistory.text_new')}}</font>)</b>@endif
        </p>
        <br />
        <table data-nx="data" class=main width=100% border=1 cellspacing=0 cellpadding=5>
        <tr valign=top><td class=comment>{{ $item['bodyHtml'] }}</td></tr>
        </table>
        <br />
    @endforeach
    {{ \App\Support\Frame::close() }}
    @if (($postcount ?? 0) > ($perpage ?? 15))
        {{ $pagerbottom ?? '' }}
    @endif
@elseif ($action === 'viewcomments')
    <h1>{{ __('legacy/userhistory.text_comments_history_for')}}{{ $subject }}</h1>
    @if (($commentcount ?? 0) > ($perpage ?? 15))
        {{ $pagertop ?? '' }}
    @endif
    {{ \App\Support\Frame::open('', false, 10, '100%', 'left') }}
    @foreach ($items ?? [] as $item)
        <p class=sub>
        {{ $item['added'] }}&nbsp;---&nbsp;<b>{{ __('legacy/userhistory.text_torrent') }}&nbsp;</b>
        @if ($item['torrentName'] !== '')
            <a href=details.php?id={{ $item['torrentid'] }}&tocomm=1&hit=1>{{ $item['torrentName'] }}</a>
        @else
            [Deleted]
        @endif
        &nbsp;---&nbsp;<b>{{ __('legacy/userhistory.text_comment') }}&nbsp;</b>#<a href=details.php?id={{ $item['torrentid'] }}&tocomm=1&hit=1{{ $item['pageUrl'] }}>{{ $item['commentid'] }}</a>
        </p>
        <br />
        <table data-nx="data" class=main width=100% border=1 cellspacing=0 cellpadding=5>
        <tr valign=top><td class=comment>{{ $item['bodyHtml'] }}</td></tr>
        </table>
        <br />
    @endforeach
    {{ \App\Support\Frame::close() }}
    @if (($commentcount ?? 0) > ($perpage ?? 15))
        {{ $pagerbottom ?? '' }}
    @endif
@endif
@endsection
