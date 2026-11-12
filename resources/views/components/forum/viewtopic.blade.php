@props(['topic'])
<nav class="nx-crumbs" aria-label="breadcrumbs">
    <a href="/web/index">{{ $topic->sitename }}</a>
    <span class="nx-crumbs__sep" aria-hidden="true">›</span>
    <a href="/forums">{{ trim(__('forums.text_forums')) }}</a>
    <span class="nx-crumbs__sep" aria-hidden="true">›</span>
    <a href="{{ request()->getPathInfo() }}?action=viewforum&amp;forumid={{ $topic->forumid }}">{{ $topic->forumname }}</a>
</nav>
<x-page id="top" :title="$topic->subject" :subtitle="$topic->locked ? __('forums.text_locked') : null" width="list">
    <x-slot:actions>
        @if ($topic->mayPost)
            <a class="nx-btn nx-btn--primary" href="{{ request()->getPathInfo() }}?action=reply&amp;topicid={{ $topic->topicid }}" title="{{ __('forums.title_reply_directly') }}">{{ __('forums.text_add_reply') }}</a>
        @endif
    </x-slot:actions>
    <x-forum.pager :page="$topic->page" :pages="$topic->pages" :href="$topic->pagerHref()" :items="$topic->pagerItems()" label="Pagination top" />
    <div class="nx-postbar">
        <span>{{ __('forums.there_is') }}<b>{{ $topic->views }}</b>{{ __('forums.hits_on_this_topic') }}</span>
        <span class="nx-postbar__actions">
            @if ($topic->isMod)
                <details class="nx-modtools">
                    <summary title="{{ __('forums.text_mod_tools') }}"><span class="nx-sr-only">{{ __('forums.text_mod_tools') }}</span><span aria-hidden="true">⋯</span></summary>
                    <span class="nx-modtools__panel">
                        <form method="post" action="/web/forums/setsticky">@csrf
                            <input type="hidden" name="topicid" value="{{ $topic->topicid }}" />
                            <input type="hidden" name="returnto" value="{{ $topic->requestUri }}" />
                            <input type="hidden" name="sticky" value="{{ $topic->sticky ? 'no' : 'yes' }}" />
                            <input type="submit" class="nx-btn" value="{{ $topic->sticky ? __('forums.submit_unsticky') : __('forums.submit_sticky') }}"  data-submit-once />
                        </form>
                        <form method="post" action="/web/forums/setlocked">@csrf
                            <input type="hidden" name="topicid" value="{{ $topic->topicid }}" />
                            <input type="hidden" name="returnto" value="{{ $topic->requestUri }}" />
                            <input type="hidden" name="locked" value="{{ $topic->locked ? 0 : 1 }}" />
                            <input type="submit" class="nx-btn" value="{{ $topic->locked ? __('forums.submit_unlock') : __('forums.submit_lock') }}"  data-submit-once />
                        </form>
                        <form method="post" action="/web/forums/deletetopic">
                            @csrf
                            <input type="hidden" name="topicid" value="{{ $topic->topicid }}" />
                            <input type="hidden" name="forumid" value="{{ $topic->forumid }}" />
                            <input type="submit" class="nx-btn" value="{{ __('forums.submit_delete_topic') }}" data-confirm="{{ __('forums.confirm_delete_topic') }}"  data-submit-once />
                        </form>
                        <form method="post" action="/web/forums/movetopic?topicid={{ $topic->topicid }}">@csrf
                            {{ __('forums.text_move_thread_to') }}
                            <select class="med" name="forumid">
                                @foreach ($topic->moveForums as $forum)
                                    <option value="{{ $forum['id'] }}">{{ $forum['name'] }}</option>
                                @endforeach
                            </select>
                            <input type="submit" class="nx-btn" value="{{ __('forums.submit_move') }}"  data-submit-once />
                        </form>
                        <form method="post" action="/web/forums/hltopic?topicid={{ $topic->topicid }}">@csrf
                            {{ __('forums.text_highlight_topic') }}
                            <select class="med" name="color">@foreach ($topic->highlightColorOptions as $opt)<option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
@endforeach</select>
                            <input type="hidden" name="returnto" value="{{ $topic->requestUri }}" />
                            <input type="submit" class="nx-btn" value="{{ __('forums.submit_change') }}"  data-submit-once />
                        </form>
                    </span>
                </details>
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
            <b>{{ __('forums.text_quick_reply') }}</b>
            <form id="compose" name="compose" method="post" action="/web/forums/post">@csrf
                <input type="hidden" name="id" value="{{ $topic->topicid }}" />
                <input type="hidden" name="type" value="reply" />
                {{ $topic->quickReply }}
            </form>
        </div>
    @else
        {{ $topic->deniedNotice }}
    @endif
</x-page>
{{ $topic->keyScript }}
