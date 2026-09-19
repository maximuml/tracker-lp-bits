@props(['topic'])
<h1 class="nx-center"><a class="faqlink" href="forums.php">{{ $topic->sitename }}&nbsp;{{ __('legacy/forums.text_forums') }}</a>--&gt;<a class="faqlink" href="?action=viewforum&amp;forumid={{ $topic->forumid }}">{{ $topic->forumname }}</a><b>--&gt;</b><span id="top">{{ $topic->subject }}@if ($topic->locked)&nbsp;&nbsp;<b>[<span class="striking">{{ __('legacy/forums.text_locked') }}</span>]</b>@endif</span></h1>
<x-forum.pager :page="$topic->page" :pages="$topic->pages" :href="$topic->pagerHref()" :items="$topic->pagerItems()" />
<div class="nx-postbar">
    <span>{{ __('legacy/forums.there_is') }}<b>{{ $topic->views }}</b>{{ __('legacy/forums.hits_on_this_topic') }}</span>
    @if ($topic->mayPost)
        <a href="?action=reply&amp;topicid={{ $topic->topicid }}"><img class="f_reply" src="pic/trans.gif" alt="Add Reply" title="{{ __('legacy/forums.title_reply_directly') }}" /></a>
    @endif
</div>
{{ $topic->frameOpen }}
@foreach ($topic->posts as $post)
    <x-forum.post :post="$post" />
    @if ($post->isLast)
        <span id="last"></span>
    @endif
@endforeach
@if ($topic->isMod)
    </td></tr><tr><td class="toolbox nx-modbar">
        <form method="post" action="?action=setsticky">
            <input type="hidden" name="topicid" value="{{ $topic->topicid }}" />
            <input type="hidden" name="returnto" value="{{ $topic->requestUri }}" />
            <input type="hidden" name="sticky" value="{{ $topic->sticky ? 'no' : 'yes' }}" />
            <input type="submit" class="medium" value="{{ $topic->sticky ? __('legacy/forums.submit_unsticky') : __('legacy/forums.submit_sticky') }}" />
        </form>
        <form method="post" action="?action=setlocked">
            <input type="hidden" name="topicid" value="{{ $topic->topicid }}" />
            <input type="hidden" name="returnto" value="{{ $topic->requestUri }}" />
            <input type="hidden" name="locked" value="{{ $topic->locked ? 0 : 1 }}" />
            <input type="submit" class="medium" value="{{ $topic->locked ? __('legacy/forums.submit_unlock') : __('legacy/forums.submit_lock') }}" />
        </form>
        <form method="get" action="?">
            <input type="hidden" name="action" value="deletetopic" />
            <input type="hidden" name="topicid" value="{{ $topic->topicid }}" />
            <input type="hidden" name="forumid" value="{{ $topic->forumid }}" />
            <input type="submit" class="medium" value="{{ __('legacy/forums.submit_delete_topic') }}" />
        </form>
        <form method="post" action="?action=movetopic&amp;topicid={{ $topic->topicid }}">
            {{ __('legacy/forums.text_move_thread_to') }}
            <select class="med" name="forumid">
                @foreach ($topic->moveForums as $forum)
                    <option value="{{ $forum['id'] }}">{{ $forum['name'] }}</option>
                @endforeach
            </select>
            <input type="submit" class="medium" value="{{ __('legacy/forums.submit_move') }}" />
        </form>
        <form method="post" action="?action=hltopic&amp;topicid={{ $topic->topicid }}">
            {{ __('legacy/forums.text_highlight_topic') }}
            <select class="med" name="color">{{ $topic->highlightColorOptions }}</select>
            <input type="hidden" name="returnto" value="{{ $topic->requestUri }}" />
            <input type="submit" class="medium" value="{{ __('legacy/forums.submit_change') }}" />
        </form>
@endif
{{ $topic->frameClose }}
<x-forum.pager :page="$topic->page" :pages="$topic->pages" :href="$topic->pagerHref()" :items="$topic->pagerItems()" />
@if ($topic->mayPost)
    <div class="nx-quickreply">
        <b>{{ __('legacy/forums.text_quick_reply') }}</b>
        <form id="compose" name="compose" method="post" action="?action=post">
            <input type="hidden" name="id" value="{{ $topic->topicid }}" />
            <input type="hidden" name="type" value="reply" />
            {{ $topic->quickReply }}
        </form>
    </div>
    <p class="nx-center"><a class="index" href="?action=reply&amp;topicid={{ $topic->topicid }}">{{ __('legacy/forums.text_add_reply') }}</a></p>
@else
    <p>{{ $topic->deniedNotice }}</p>
@endif
{{ $topic->keyScript }}
