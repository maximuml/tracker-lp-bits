@props(['post'])
<article class="nx-post" id="pid{{ $post->id }}">
    <header class="nx-post__head">
        <span class="nx-post__meta">
            <a href="{{ $post->anchorUrl }}">#{{ $post->id }}</a>
            <span class="nx-dim">{{ __('legacy/forums.text_by') }}</span> {{ $post->by }}
            <span class="nx-dim">{{ __('legacy/forums.text_at') }}</span> <x-time :value="$post->addedRaw" />
            <span class="nx-dim">|</span>
            <a href="{{ $post->authorToggleUrl }}">{{ $post->authorToggleLabel }}</a>
        </span>
        <span class="nx-post__num">
            <span class="big">{{ __('legacy/forums.text_number') }}<b>{{ $post->number }}</b>{{ __('legacy/forums.text_lou') }}</span>
            <a href="#top"><img class="top" src="pic/trans.gif" alt="Top" title="{{ __('legacy/forums.text_back_to_top') }}" /></a>
        </span>
    </header>
    <div class="nx-post__grid">
        <aside class="nx-post__user">
            {{ $post->avatarImage }}
            <img class="nx-post__class" alt="{{ $post->className }}" title="{{ $post->className }}" src="{{ $post->classImage }}" />
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
            @if ($post->online)
                <img class="f_online" src="pic/trans.gif" alt="Online" title="{{ __('legacy/forums.title_online') }}" />
            @else
                <img class="f_offline" src="pic/trans.gif" alt="Offline" title="{{ __('legacy/forums.title_offline') }}" />
            @endif
            <a href="sendmessage.php?receiver={{ $post->posterId }}"><img class="f_pm" src="pic/trans.gif" alt="PM" title="{{ __('legacy/forums.title_send_message_to') }}{{ $post->posterName }}" /></a>
            <a href="report.php?forumpost={{ $post->id }}"><img class="f_report" src="pic/trans.gif" alt="Report" title="{{ __('legacy/forums.title_report_this_post') }}" /></a>
        </span>
        <span class="nx-post__tools">
            @if ($post->canQuote)
                <a href="?action=quotepost&amp;postid={{ $post->id }}"><img class="f_quote" src="pic/trans.gif" alt="Quote" title="{{ __('legacy/forums.title_reply_with_quote') }}" /></a>
            @endif
            @if ($post->canDelete)
                <a href="?action=deletepost&amp;postid={{ $post->id }}"><img class="f_delete" src="pic/trans.gif" alt="Delete" title="{{ __('legacy/forums.title_delete_post') }}" /></a>
            @endif
            @if ($post->canEdit)
                <a href="?action=editpost&amp;postid={{ $post->id }}"><img class="f_edit" src="pic/trans.gif" alt="Edit" title="{{ __('legacy/forums.title_edit_post') }}" /></a>
            @endif
        </span>
    </footer>
</article>
