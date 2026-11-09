@if ($forums !== null)
<x-page :title="trim($forums->siteName).' '.trim(__('forums.text_forums'))" width="list">
    <x-slot:tabs><x-forum.nav-tabs active="forums" /></x-slot:tabs>
    @if ($forums->canManageForums)
        <x-slot:actions><a class="nx-btn" href="/forummanage">{{ __('forums.text_forum_manager') }}</a></x-slot:actions>
    @endif
    <x-forum.index-table :sections="$forums->sections" />
    @if ($forums->stats !== null)
        <x-forum.stats :stats="$forums->stats" />
    @endif
</x-page>
@endif
