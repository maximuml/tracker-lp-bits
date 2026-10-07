@props(['list'])
<h1 class="text-center"><a class="faqlink" href="/forums">{{ $list->siteName }}&nbsp;{{ __('forums.text_forums') }}</a>--&gt;<a class="faqlink" href="/forums?action=viewforum&amp;forumid={{ $list->forumId }}">{{ $list->forumName }}</a></h1>
<br />
@if (! $list->mayPost)
    <p><i>{{ __('forums.text_unpermitted_starting_new_topics') }}</i></p>
@endif
<div class="my-1 flex items-center justify-between px-1 py-0.5">
    <div>
        @if ($list->moderators !== null)
            &nbsp;&nbsp;<img class="forum_mod" src="pic/trans.gif" alt="Moderator" title="{{ __('forums.col_moderator') }}" />&nbsp;{{ $list->moderators }}
        @endif
    </div>
    <div class="whitespace-nowrap">
        @if ($list->mayPost)
            <a href="{{ request()->getPathInfo() }}?action=newtopic&amp;forumid={{ $list->forumId }}"><img class="f_new" src="pic/trans.gif" alt="New Topic" title="{{ __('forums.title_new_topic') }}" /></a>&nbsp;&nbsp;
        @endif
    </div>
</div>
@if ($list->topics !== [])
    <x-data-table :caption="$list->forumName" captionHidden>
        <x-slot:head>
            <thead>
                <tr>
                    <th class="w-[99%]" scope="col">{{ __('forums.col_topic') }}</th>
                    <th scope="col"><a href="{{ request()->getPathInfo() }}?action=viewforum&amp;forumid={{ $list->forumId }}{{ $list->addParam() }}&amp;sort={{ $list->sortToggles()['first'] }}" title="{{ $list->sortToggles()['firstTitle'] }}">{{ __('forums.col_author') }}</a></th>
                    <th scope="col">{{ __('forums.col_replies') }}/{{ __('forums.col_views') }}</th>
                    <th scope="col"><a href="{{ request()->getPathInfo() }}?action=viewforum&amp;forumid={{ $list->forumId }}{{ $list->addParam() }}&amp;sort={{ $list->sortToggles()['last'] }}" title="{{ $list->sortToggles()['lastTitle'] }}">{{ __('forums.col_last_post') }}</a></th>
                </tr>
            </thead>
        </x-slot:head>
        @foreach ($list->topics as $row)
            <x-forum.topic-row :row="$row" />
        @endforeach
        <tr>
            <td>
                <form method="get" action="/forums" class="flex items-center gap-1.5"><b>{{ __('forums.text_fast_search') }}</b><input type="hidden" name="action" value="viewforum" /><input type="hidden" name="forumid" value="{{ $list->forumId }}" /><input type="text" class="w-[11.25rem]" name="search" />&nbsp;<x-button type="submit">{{ __('forums.text_go') }}</x-button></form>
            </td>
            <td colspan="3">
                <span id="order"><span><b>{{ __('forums.text_order') }}</b></span>
                <span id="orderlist" class="dropmenu nx-hidden"><ul>
                    <li><a href="{{ request()->getPathInfo() }}?action=viewforum&amp;forumid={{ $list->forumId }}{{ $list->addParam() }}&amp;sort=firstpostdesc">{{ __('forums.text_topic_desc') }}</a></li>
                    <li><a href="{{ request()->getPathInfo() }}?action=viewforum&amp;forumid={{ $list->forumId }}{{ $list->addParam() }}&amp;sort=firstpostasc">{{ __('forums.text_topic_asc') }}</a></li>
                    <li><a href="{{ request()->getPathInfo() }}?action=viewforum&amp;forumid={{ $list->forumId }}{{ $list->addParam() }}&amp;sort=lastpostdesc">{{ __('forums.text_post_desc') }}</a></li>
                    <li><a href="{{ request()->getPathInfo() }}?action=viewforum&amp;forumid={{ $list->forumId }}{{ $list->addParam() }}&amp;sort=lastpostasc">{{ __('forums.text_post_asc') }}</a></li>
                </ul>
                </span>
                </span>
            </td>
        </tr>
    </x-data-table>
    <x-forum.pager :page="$list->page" :pages="$list->pages" :href="$list->pagerHref()" :items="$list->pagerItems()" />
    @foreach ($list->tooltips as $tip)
        @if ($loop->first)<div class="nx-hidden">@endif
        <div id="{{ $tip['id'] }}">{{ $tip['content'] }}@if($tip['contentTail'] ?? '')<br />{{ $tip['contentTail'] }}@endif</div>
        @if ($loop->last)</div>@endif
    @endforeach
@else
    <p>{{ __('forums.text_no_topics_found') }}</p>
@endif
