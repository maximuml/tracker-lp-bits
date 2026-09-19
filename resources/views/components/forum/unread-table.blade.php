@props(['list'])
<h1 class="nx-center"><a class="faqlink" href="forums.php">{{ $list->siteName }}&nbsp;{{ __('legacy/forums.text_forums') }}</a>--&gt;{{ __('legacy/forums.text_topics_with_unread_posts') }}</h1>
@if ($list->topics !== [])
    <table data-nx="data" class="nx-forum-table">
        <tbody>
        <tr>
            <th class="colhead nx-forum-table__name" scope="col">{{ __('legacy/forums.col_topic') }}</th>
            <th class="colhead" scope="col">{{ __('legacy/forums.col_forum') }}</th>
        </tr>
        @foreach ($list->topics as $row)
            <tr>
                <td class="rowfollow">
                    <div class="nx-forum-row">
                        <img class="unlockednew" src="pic/trans.gif" alt="unread" title="{{ __('legacy/forums.title_unread') }}" />
                        <div>
                            <a href="?action=viewtopic&amp;topicid={{ $row->topicId }}@if ($row->jumpToPostId !== null)&amp;page=p{{ $row->jumpToPostId }}#pid{{ $row->jumpToPostId }}@endif">@if ($row->hlcolor > 0)<b class="nx-hl-{{ $row->hlcolor }}">{{ $row->subject }}</b>@else{{ $row->subject }}@endif</a>
                        </div>
                    </div>
                </td>
                <td class="rowfollow"><a href="?action=viewforum&amp;forumid={{ $row->forumId }}"><b>{{ $row->forumName }}</b></a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="nx-forum-unread-actions">
        <form method="get" action="?"><input type="hidden" name="catchup" value="1" /><x-button type="submit">{{ __('legacy/forums.text_catch_up') }}</x-button></form>
        @if ($list->moreBeforePostId !== null)
            <form method="get" action="?"><input type="hidden" name="action" value="viewunread" /><input type="hidden" name="beforepostid" value="{{ $list->moreBeforePostId }}" /><x-button type="submit">{{ __('legacy/forums.submit_show_more') }}</x-button></form>
        @endif
    </div>
@else
    <p>{{ __('legacy/forums.text_nothing_found') }}</p>
@endif
