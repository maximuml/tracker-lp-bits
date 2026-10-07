@props(['stats'])
<h2 class="nx-forum-stats__title">{{ __('forums.text_stats') }}</h2>
<div class="nx-forum-stats text">
    {{ __('forums.text_our_members_have') }}<b>{{ $stats->posts }}</b>{{ __('forums.text_posts_in_topics') }}<b>{{ $stats->topics }}</b>{{ __('forums.text_in_topics') }}<b class="new">{{ $stats->todayPosts }}</b>{{ __('forums.text_new_post') }}{{ $stats->todayPostsPlural() }}{{ __('forums.text_posts_today') }}<br /><br />
    @if ($stats->activeUsers > 0)
        {{ __('forums.text_there') }}{{ $stats->activeUsersIsAre() }}<b>{{ $stats->activeUsers }}</b>{{ __('forums.text_online_user') }}{{ $stats->activeUsersPlural() }}{{ __('forums.text_in_forum_now') }}
    @else
        {{ __('forums.text_no_active_users') }}
    @endif
</div>
