@props(['list'])
<h1 class="text-center"><a class="faqlink" href="forums.php">{{ $list->siteName }}&nbsp;{{ __('legacy/forums.text_forums') }}</a>--&gt;{{ __('legacy/forums.text_topics_with_unread_posts') }}</h1>
@if ($list->topics !== [])
    <x-data-table :caption="__('legacy/forums.text_topics_with_unread_posts')" captionHidden>
        <x-slot:head>
            <thead>
                <tr>
                    <th class="w-[99%]" scope="col">{{ __('legacy/forums.col_topic') }}</th>
                    <th scope="col">{{ __('legacy/forums.col_forum') }}</th>
                </tr>
            </thead>
        </x-slot:head>
        @foreach ($list->topics as $row)
            <tr>
                <td>
                    <div class="flex items-start gap-2">
                        <img class="unlockednew" src="pic/trans.gif" alt="unread" title="{{ __('legacy/forums.title_unread') }}" />
                        <div>
                            <a href="?action=viewtopic&amp;topicid={{ $row->topicId }}@if ($row->jumpToPostId !== null)&amp;page=p{{ $row->jumpToPostId }}#pid{{ $row->jumpToPostId }}@endif">@if ($row->hlcolor > 0)<b class="nx-hl-{{ $row->hlcolor }}">{{ $row->subject }}</b>@else{{ $row->subject }}@endif</a>
                        </div>
                    </div>
                </td>
                <td><a href="?action=viewforum&amp;forumid={{ $row->forumId }}"><b>{{ $row->forumName }}</b></a></td>
            </tr>
        @endforeach
    </x-data-table>
    <div class="mt-2 flex gap-2">
        <form method="get" action="?"><input type="hidden" name="catchup" value="1" /><x-button type="submit">{{ __('legacy/forums.text_catch_up') }}</x-button></form>
        @if ($list->moreBeforePostId !== null)
            <form method="get" action="?"><input type="hidden" name="action" value="viewunread" /><input type="hidden" name="beforepostid" value="{{ $list->moreBeforePostId }}" /><x-button type="submit">{{ __('legacy/forums.submit_show_more') }}</x-button></form>
        @endif
    </div>
@else
    <p>{{ __('legacy/forums.text_nothing_found') }}</p>
@endif
