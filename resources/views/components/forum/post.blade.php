@props(['post'])
<article class="nx-post" id="pid{{ $post->id }}">
    <header class="nx-post__head">
        <div class="nx-post__meta">
            <a href="{{ $post->anchorUrl }}">#{{ $post->id }}</a>
            <span class="text-nxm-text-dim">{{ __('legacy/forums.text_by') }}</span> {{ $post->by }}
            <span class="text-nxm-text-dim">{{ __('legacy/forums.text_at') }}</span> <x-time :value="$post->addedRaw" />
            <span class="text-nxm-text-dim">|</span>
            <a href="{{ $post->authorToggleUrl }}">{{ $post->authorToggleLabel }}</a>
        </div>
        <span class="nx-post__num">
            <span class="big">{{ __('legacy/forums.text_number') }}<b>{{ $post->number }}</b>{{ __('legacy/forums.text_lou') }}</span>
            <a class="nx-post__top" href="#top" title="{{ __('legacy/forums.text_back_to_top') }}">↑</a>
        </span>
    </header>
    <div class="nx-post__grid">
        <aside class="nx-post__user">
            <div class="nx-post__avatar">{{ $post->avatarImage }}</div>
            <span class="nx-post__rank" title="{{ $post->className }}">{{ $post->className }}</span>
            <dl class="nx-post__stats">
                <div><dt>{{ __('legacy/forums.text_posts') }}</dt><dd>{{ $post->postCount }}</dd></div>
                <div><dt>{{ __('legacy/forums.text_ul') }}</dt><dd>{{ $post->uploaded }}</dd></div>
                <div><dt>{{ __('legacy/forums.text_dl') }}</dt><dd>{{ $post->downloaded }}</dd></div>
                <div><dt>{{ __('legacy/forums.text_ratio') }}</dt><dd>{{ $post->ratio }}</dd></div>
            </dl>
        </aside>
        <div class="nx-post__body">
            <div id="pid{{ $post->id }}body">{{ $post->body }}</div>
            @if ($post->signature)
                <p class="nx-post__sig">____________________<br />{{ $post->signature }}</p>
            @endif
            @if ($post->editedBy !== null)
                <p class="nx-post__edited small">{{ __('legacy/forums.text_last_edited_by') }}{{ $post->editedBy }}{{ __('legacy/forums.text_last_edit_at') }}<x-time :value="$post->editedAtRaw" /></p>
            @endif
        </div>
    </div>
    <footer class="nx-post__foot">
        <span class="nx-post__contact">
            <span class="nx-post__status{{ $post->online ? ' nx-post__status--on' : '' }}" title="{{ $post->online ? __('legacy/forums.title_online') : __('legacy/forums.title_offline') }}"></span>
            <a class="nx-postbtn" href="/web/sendmessage?receiver={{ $post->posterId }}" title="{{ __('legacy/forums.title_send_message_to') }}{{ $post->posterName }}">{{ __('legacy/forums.text_pm') }}</a>
            <a class="nx-postbtn" href="/web/report?forumpost={{ $post->id }}" title="{{ __('legacy/forums.title_report_this_post') }}">{{ __('legacy/forums.text_report') }}</a>
        </span>
        <span class="nx-post__tools">
            @if ($post->canQuote)
                <a class="nx-postbtn" href="{{ request()->getPathInfo() }}?action=quotepost&amp;postid={{ $post->id }}" title="{{ __('legacy/forums.title_reply_with_quote') }}">{{ __('legacy/forums.text_quote') }}</a>
            @endif
            @if ($post->canDelete)
                <a class="nx-postbtn nx-postbtn--danger" href="{{ request()->getPathInfo() }}?action=deletepost&amp;postid={{ $post->id }}" title="{{ __('legacy/forums.title_delete_post') }}">{{ __('legacy/forums.text_delete') }}</a>
            @endif
            @if ($post->canEdit)
                <a class="nx-postbtn" href="{{ request()->getPathInfo() }}?action=editpost&amp;postid={{ $post->id }}" title="{{ __('legacy/forums.title_edit_post') }}">{{ __('legacy/forums.text_edit') }}</a>
            @endif
        </span>
    </footer>
</article>
