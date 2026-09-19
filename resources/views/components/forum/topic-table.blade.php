@props(['list'])
<h1 class="nx-center"><a class="faqlink" href="forums.php">{{ $list->siteName }}&nbsp;{{ __('legacy/forums.text_forums') }}</a>--&gt;<a class="faqlink" href="forums.php?action=viewforum&amp;forumid={{ $list->forumId }}">{{ $list->forumName }}</a></h1>
<br />
@if (! $list->mayPost)
    <p><i>{{ __('legacy/forums.text_unpermitted_starting_new_topics') }}</i></p>
@endif
<div class="nx-forum-toolbar">
    <div>
        @if ($list->moderators !== null)
            &nbsp;&nbsp;<img class="forum_mod" src="pic/trans.gif" alt="Moderator" title="{{ __('legacy/forums.col_moderator') }}" />&nbsp;{{ $list->moderators }}
        @endif
    </div>
    <div class="nx-forum-toolbar__actions">
        @if ($list->mayPost)
            <a href="?action=newtopic&amp;forumid={{ $list->forumId }}"><img class="f_new" src="pic/trans.gif" alt="New Topic" title="{{ __('legacy/forums.title_new_topic') }}" /></a>&nbsp;&nbsp;
        @endif
    </div>
</div>
@if ($list->topics !== [])
    <table data-nx="data" class="nx-forum-table">
        <tbody>
        <tr>
            <th class="colhead nx-forum-table__name" scope="col">{{ __('legacy/forums.col_topic') }}</th>
            <th class="colhead" scope="col"><a href="?action=viewforum&amp;forumid={{ $list->forumId }}{{ $list->addParam() }}&amp;sort={{ $list->sortToggles()['first'] }}" title="{{ $list->sortToggles()['firstTitle'] }}">{{ __('legacy/forums.col_author') }}</a></th>
            <th class="colhead" scope="col">{{ __('legacy/forums.col_replies') }}/{{ __('legacy/forums.col_views') }}</th>
            <th class="colhead" scope="col"><a href="?action=viewforum&amp;forumid={{ $list->forumId }}{{ $list->addParam() }}&amp;sort={{ $list->sortToggles()['last'] }}" title="{{ $list->sortToggles()['lastTitle'] }}">{{ __('legacy/forums.col_last_post') }}</a></th>
        </tr>
        @foreach ($list->topics as $row)
            <x-forum.topic-row :row="$row" />
        @endforeach
        <tr>
            <td class="nx-left">
                <form method="get" action="forums.php" class="nx-fast-search"><b>{{ __('legacy/forums.text_fast_search') }}</b><input type="hidden" name="action" value="viewforum" /><input type="hidden" name="forumid" value="{{ $list->forumId }}" /><input type="text" class="nx-fast-search__input" name="search" />&nbsp;<x-button type="submit">{{ __('legacy/forums.text_go') }}</x-button></form>
            </td>
            <td class="nx-left" colspan="3">
                <span id="order"><span><b>{{ __('legacy/forums.text_order') }}</b></span>
                <span id="orderlist" class="dropmenu nx-hidden"><ul>
                    <li><a href="?action=viewforum&amp;forumid={{ $list->forumId }}{{ $list->addParam() }}&amp;sort=firstpostdesc">{{ __('legacy/forums.text_topic_desc') }}</a></li>
                    <li><a href="?action=viewforum&amp;forumid={{ $list->forumId }}{{ $list->addParam() }}&amp;sort=firstpostasc">{{ __('legacy/forums.text_topic_asc') }}</a></li>
                    <li><a href="?action=viewforum&amp;forumid={{ $list->forumId }}{{ $list->addParam() }}&amp;sort=lastpostdesc">{{ __('legacy/forums.text_post_desc') }}</a></li>
                    <li><a href="?action=viewforum&amp;forumid={{ $list->forumId }}{{ $list->addParam() }}&amp;sort=lastpostasc">{{ __('legacy/forums.text_post_asc') }}</a></li>
                </ul>
                </span>
                </span>
            </td>
        </tr>
        </tbody>
    </table>
    <x-forum.pager :page="$list->page" :pages="$list->pages" :href="$list->pagerHref()" :items="$list->pagerItems()" />
    @foreach ($list->tooltips as $tip)
        @if ($loop->first)<div class="nx-hidden">@endif
        <div id="{{ $tip['id'] }}">{{ $tip['content'] }}</div>
        @if ($loop->last)</div>@endif
    @endforeach
@else
    <p>{{ __('legacy/forums.text_no_topics_found') }}</p>
@endif
