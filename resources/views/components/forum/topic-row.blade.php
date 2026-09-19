@props(['row'])
<tr>
    <td class="rowfollow">
        <div class="nx-forum-row">
            <img class="{{ $row->stateIcon()[0] }}" src="pic/trans.gif" alt="{{ $row->stateIcon()[1] }}" title="{{ $row->stateIcon()[2] }}" />
            <div>
                @if ($row->sticky)
                    <img class="sticky" src="pic/trans.gif" alt="Sticky" title="{{ __('legacy/forums.title_sticky') }}" />&nbsp;&nbsp;
                @endif
                <a href="?action=viewtopic&amp;forumid={{ $row->forumId }}&amp;topicid={{ $row->id }}"@if ($row->tooltipId !== null) data-domtt-src="{{ $row->tooltipId }}"@endif>@if ($row->hlcolor > 0)<b class="nx-hl-{{ $row->hlcolor }}">{{ $row->subject }}</b>@else{{ $row->subject }}@endif</a>
                @if ($row->visiblePages !== [])
                    [<img class="multipage" src="pic/trans.gif" alt="multi-page" />
                    @foreach ($row->visiblePages as $p)
                        @if ($p === '…') … @else <a href="?action=viewtopic&amp;topicid={{ $row->id }}&amp;page={{ $p - 1 }}">{{ $p }}</a>@endif
                    @endforeach
                    ]
                @endif
                @if ($row->jumpToPostId !== null)
                    &nbsp;&nbsp;<a href="?action=viewtopic&amp;forumid={{ $row->forumId }}&amp;topicid={{ $row->id }}&amp;page=p{{ $row->jumpToPostId }}#pid{{ $row->jumpToPostId }}" title="{{ __('legacy/forums.title_jump_to_unread') }}"><span class="small new"><b>{{ __('legacy/forums.text_new') }}</b></span></a>
                @endif
            </div>
        </div>
    </td>
    <td class="rowfollow nx-center">{{ $row->author }}<br />@if ($row->firstAddedRecent)<span class="new small">{{ $row->firstAdded }}</span>@else<span class="nx-dim small">{{ $row->firstAdded }}</span>@endif</td>
    <td class="rowfollow nx-center">{{ $row->replies }} / <span class="nx-dim">{{ number_format($row->views) }}</span></td>
    <td class="rowfollow nx-nowrap nx-center"><x-time :value="$row->lastPostAt" /><br />{{ $row->lastPoster }}</td>
</tr>
