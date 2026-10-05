@if ($commentCount)
    <br /><br />
    <h1 class="text-center" id="startcomments">{{ __('legacy/details.h1_user_comments') }}</h1>

    {{ $commentPagerTop }}
    {{ $commentsTableHtml }}
    {{ $commentPagerBottom }}
@endif

<br /><br />
<div class="nx-box text-center">
    <div class="p-[10pt] text-center">
            <b>{{ __('legacy/details.text_quick_comment') }}</b><br /><br />
            <form id="compose" name="comment" method="post" action="{{ 'comment.php?action=add&type=torrent' }}">
                <input type="hidden" name="pid" value="{{ $id }}" />
                {{ $quickReplyHtml }}
            </form>
    </div>
</div>
<p class="text-center"><a class="index" href="{{ 'comment.php?action=add&pid=' . $id . '&type=torrent' }}">{{ __('legacy/details.text_add_a_comment') }}</a></p>
