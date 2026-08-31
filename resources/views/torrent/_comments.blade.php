@php
$showComments = ($CURUSER['showcomment'] ?? '') !== 'no';
@endphp

@if ($showComments)
    @php
    $count = \app(\App\Repositories\TorrentDetailRepository::class)->getCommentCount($id);
    @endphp

    @if ($count)
        <br /><br />
        <h1 align="center" id="startcomments">{{ $lang_details['h1_user_comments'] }}</h1>

        @php
        list($pagertop, $pagerbottom, $limit, $offset, $rpp) = \App\Support\Pagination::pager(10, $count, "details.php?id=$id&cmtpage=1&", ['lastpagedefault' => 1], 'page');
        $allrows = \app(\App\Repositories\TorrentDetailRepository::class)->getComments($id, (int) $offset, (int) $rpp);
        @endphp

        {!! $pagertop !!}
        {!! \App\Support\Comment::table($allrows, 'torrent', $id) !!}
        {!! $pagerbottom !!}
    @endif
@endif

<br /><br />
<table style="border:1px solid #000000;">
    <tr>
        <td class="text" align="center">
            <b>{{ $lang_details['text_quick_comment'] }}</b><br /><br />
            <form id="compose" name="comment" method="post" action="{{ 'comment.php?action=add&type=torrent' }}" onsubmit="return postvalid(this);">
                <input type="hidden" name="pid" value="{{ $id }}" />
                {!! \App\Support\Html::quickReply('comment', 'body', $lang_details['submit_add_comment']) !!}
            </form>
        </td>
    </tr>
</table>
<p align="center"><a class="index" href="{{ 'comment.php?action=add&pid=' . $id . '&type=torrent' }}">{{ $lang_details['text_add_a_comment'] }}</a></p>
