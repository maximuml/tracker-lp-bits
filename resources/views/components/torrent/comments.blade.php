@if ($commentCount)
    <br /><br />
    <h2 class="text-center" id="startcomments">{{ __('details.h1_user_comments') }}</h2>

    @if ($commentPager !== [])
<x-pagination :page="$commentPager['page']" :pages="$commentPager['pages']" :href="$commentPager['href']" :prev-label="$commentPager['prevLabel']" :next-label="$commentPager['nextLabel']" :zero-based="$commentPager['zeroBased']" label="Pagination top" />
@endif
@endif

<livewire:comment-section :parent-id="$id" :enabled="$commentsEnabled" />

@if ($commentCount)
    @if ($commentPager !== [])
<x-pagination :page="$commentPager['page']" :pages="$commentPager['pages']" :href="$commentPager['href']" :prev-label="$commentPager['prevLabel']" :next-label="$commentPager['nextLabel']" :zero-based="$commentPager['zeroBased']" label="Pagination bottom" />
@endif
@endif

<p class="text-center"><a class="index" href="{{ '/comment/add?pid=' . $id . '&type=torrent' }}">{{ __('details.text_add_a_comment') }}</a></p>
