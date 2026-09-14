@if ($commentCount)
    <br /><br />
    <h1 align="center" id="startcomments">{{ $lang_details['h1_user_comments'] }}</h1>

    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($commentPagerTop))
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($commentsTableHtml))
    @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($commentPagerBottom))
@endif

<br /><br />
<table style="border:1px solid #000000;">
    <tr>
        <td class="text" align="center">
            <b>{{ $lang_details['text_quick_comment'] }}</b><br /><br />
            <form id="compose" name="comment" method="post" action="{{ 'comment.php?action=add&type=torrent' }}">
                <input type="hidden" name="pid" value="{{ $id }}" />
                @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($quickReplyHtml))
            </form>
        </td>
    </tr>
</table>
<p align="center"><a class="index" href="{{ 'comment.php?action=add&pid=' . $id . '&type=torrent' }}">{{ $lang_details['text_add_a_comment'] }}</a></p>
