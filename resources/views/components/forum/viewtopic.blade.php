@props(['topic'])
<nav class="nx-crumbs" aria-label="breadcrumbs">
    <a href="/web/index">{{ $topic->sitename }}</a>
    <span class="nx-crumbs__sep" aria-hidden="true">›</span>
    <a href="/forums">{{ trim(__('legacy/forums.text_forums')) }}</a>
    <span class="nx-crumbs__sep" aria-hidden="true">›</span>
    <a href="{{ request()->getPathInfo() }}?action=viewforum&amp;forumid={{ $topic->forumid }}">{{ $topic->forumname }}</a>
    <span class="nx-crumbs__sep" aria-hidden="true">›</span>
    <span class="nx-crumbs__current" id="top">{{ $topic->subject }}@if ($topic->locked)&nbsp;<span class="nx-crumbs__locked">[{{ __('legacy/forums.text_locked') }}]</span>@endif</span>
</nav>
<h1 class="nx-sr-only">{{ $topic->subject }}</h1>
<x-forum.pager :page="$topic->page" :pages="$topic->pages" :href="$topic->pagerHref()" :items="$topic->pagerItems()" label="Pagination top" />
<div class="nx-postbar">
    <span>{{ __('legacy/forums.there_is') }}<b>{{ $topic->views }}</b>{{ __('legacy/forums.hits_on_this_topic') }}</span>
    <span class="nx-postbar__actions">
        @if ($topic->isMod)
            <details class="nx-modtools">
                <summary title="{{ __('legacy/forums.text_mod_tools') }}">⋯</summary>
                <span class="nx-modtools__panel">
                    <form method="post" action="/web/forums/setsticky">
                        <input type="hidden" name="topicid" value="{{ $topic->topicid }}" />
                        <input type="hidden" name="returnto" value="{{ $topic->requestUri }}" />
                        <input type="hidden" name="sticky" value="{{ $topic->sticky ? 'no' : 'yes' }}" />
                        <input type="submit" class="medium" value="{{ $topic->sticky ? __('legacy/forums.submit_unsticky') : __('legacy/forums.submit_sticky') }}" />
                    </form>
                    <form method="post" action="/web/forums/setlocked">
                        <input type="hidden" name="topicid" value="{{ $topic->topicid }}" />
                        <input type="hidden" name="returnto" value="{{ $topic->requestUri }}" />
                        <input type="hidden" name="locked" value="{{ $topic->locked ? 0 : 1 }}" />
                        <input type="submit" class="medium" value="{{ $topic->locked ? __('legacy/forums.submit_unlock') : __('legacy/forums.submit_lock') }}" />
                    </form>
                    <form method="get" action="{{ request()->getPathInfo() }}">
                        <input type="hidden" name="action" value="deletetopic" />
                        <input type="hidden" name="topicid" value="{{ $topic->topicid }}" />
                        <input type="hidden" name="forumid" value="{{ $topic->forumid }}" />
                        <input type="submit" class="medium" value="{{ __('legacy/forums.submit_delete_topic') }}" />
                    </form>
                    <form method="post" action="/web/forums/movetopic?topicid={{ $topic->topicid }}">
                        {{ __('legacy/forums.text_move_thread_to') }}
                        <select class="med" name="forumid">
                            @foreach ($topic->moveForums as $forum)
                                <option value="{{ $forum['id'] }}">{{ $forum['name'] }}</option>
                            @endforeach
                        </select>
                        <input type="submit" class="medium" value="{{ __('legacy/forums.submit_move') }}" />
                    </form>
                    <form method="post" action="/web/forums/hltopic?topicid={{ $topic->topicid }}">
                        {{ __('legacy/forums.text_highlight_topic') }}
                        <select class="med" name="color">@foreach ($topic->highlightColorOptions as $opt)<option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
@endforeach</select>
                        <input type="hidden" name="returnto" value="{{ $topic->requestUri }}" />
                        <input type="submit" class="medium" value="{{ __('legacy/forums.submit_change') }}" />
                    </form>
                </span>
            </details>
        @endif
        @if ($topic->mayPost)
            <a class="nx-postbtn" href="{{ request()->getPathInfo() }}?action=reply&amp;topicid={{ $topic->topicid }}" title="{{ __('legacy/forums.title_reply_directly') }}">{{ __('legacy/forums.text_add_reply') }}</a>
        @endif
    </span>
</div>
<x-frame :center="false">
@foreach ($topic->posts as $post)
    <x-forum.post :post="$post" />
    @if ($post->isLast)
        <span id="last"></span>
    @endif
@endforeach
</x-frame>
<x-forum.pager :page="$topic->page" :pages="$topic->pages" :href="$topic->pagerHref()" :items="$topic->pagerItems()" label="Pagination bottom" />
@if ($topic->mayPost)
    <div class="nx-quickreply">
        <b>{{ __('legacy/forums.text_quick_reply') }}</b>
        <form id="compose" name="compose" method="post" action="/web/forums/post">
            <input type="hidden" name="id" value="{{ $topic->topicid }}" />
            <input type="hidden" name="type" value="reply" />
            {{ $topic->quickReply }}
        </form>
    </div>
@else
    {{ $topic->deniedNotice }}
@endif
{{ $topic->keyScript }}
