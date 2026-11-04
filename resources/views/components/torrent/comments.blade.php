@if ($commentCount)
    <br /><br />
    <h2 class="text-center" id="startcomments">{{ __('details.h1_user_comments') }}</h2>

    {{ $commentPagerTop }}
@endif

<livewire:comment-section :parent-id="$id" :enabled="$commentsEnabled" />

@if ($commentCount)
    {{ $commentPagerBottom }}
@endif

<p class="text-center"><a class="index" href="{{ '/comment/add?pid=' . $id . '&type=torrent' }}">{{ __('details.text_add_a_comment') }}</a></p>
