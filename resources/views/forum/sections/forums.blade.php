@if ($forums !== null)
<h1 class="text-center">{{ $forums->siteName }}&nbsp;{{ __('forums.text_forums') }}</h1>
<p class="text-center">
    <a href="{{ request()->getPathInfo() }}?action=search"><b>{{ __('forums.text_search') }}</b></a> |
    <a href="{{ request()->getPathInfo() }}?action=viewunread"><b>{{ __('forums.text_view_unread') }}</b></a> |
    <a href="{{ request()->getPathInfo() }}?catchup=1"><b>{{ __('forums.text_catch_up') }}</b></a>
    @if ($forums->canManageForums)
        | <a href="/forummanage"><b>{{ __('forums.text_forum_manager') }}</b></a>
    @endif
</p>
<x-forum.index-table :sections="$forums->sections" />
@if ($forums->stats !== null)
    <x-forum.stats :stats="$forums->stats" />
@endif
@endif
