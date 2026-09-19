@if ($forums !== null)
<h1 class="nx-center">{{ $forums->siteName }}&nbsp;{{ __('legacy/forums.text_forums') }}</h1>
<p class="nx-center">
    <a href="?action=search"><b>{{ __('legacy/forums.text_search') }}</b></a> |
    <a href="?action=viewunread"><b>{{ __('legacy/forums.text_view_unread') }}</b></a> |
    <a href="?catchup=1"><b>{{ __('legacy/forums.text_catch_up') }}</b></a>
    @if ($forums->canManageForums)
        | <a href="forummanage.php"><b>{{ __('legacy/forums.text_forum_manager') }}</b></a>
    @endif
</p>
<x-forum.index-table :sections="$forums->sections" />
@if ($forums->stats !== null)
    <x-forum.stats :stats="$forums->stats" />
@endif
@endif
