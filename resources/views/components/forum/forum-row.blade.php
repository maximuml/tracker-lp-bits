@props(['row'])
<tr>
    <td class="rowfollow">
        <div class="nx-forum-row">
            <img class="{{ $row->hasUnread ? 'unlockednew' : 'unlocked' }}" src="pic/trans.gif"
                 alt="{{ $row->hasUnread ? 'unread' : 'read' }}"
                 title="{{ $row->hasUnread ? __('legacy/forums.title_unread') : __('legacy/forums.title_read') }}" />
            <div>
                <a href="?action=viewforum&amp;forumid={{ $row->id }}"><b class="big">{{ $row->name }}</b></a>
                @if ($row->postsToday > 0)
                    <span class="nx-forum-row__today">({{ __('legacy/forums.text_today') }}<b class="new">{{ $row->postsToday }}</b>)</span>
                @endif
                <br />{{ $row->description }}
            </div>
        </div>
    </td>
    <td class="rowfollow nx-center">{{ number_format($row->topicCount) }}</td>
    <td class="rowfollow nx-center">{{ number_format($row->postCount) }}</td>
    <td class="rowfollow nx-nowrap"><x-forum.last-post :post="$row->lastPost" /></td>
    <td class="rowfollow">
        @if ($row->moderators !== null)
            {{ $row->moderators }}
        @else
            <a href="contactstaff.php"><i>{{ __('legacy/forums.text_apply_now') }}</i></a>
        @endif
    </td>
</tr>
