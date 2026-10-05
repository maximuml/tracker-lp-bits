@props(['search'])
<div class="search">
    <div class="search_title">{{ __('legacy/forums.text_search_on_forum') }}
        @if ($search->searched)
            @if ($search->hits > 0)
                [<b class="striking"> {{ __('legacy/forums.text_found') }}{{ $search->hits }}{{ __('legacy/forums.text_num_posts') }} </b>]
            @else
                [<b class="striking"> {{ __('legacy/forums.text_nothing_found') }} </b>]
            @endif
        @endif
    </div>
    <div class="nx-search-form">
        <form method="get" action="forums.php" id="search_form" class="nx-search-form__inner">
            <input type="hidden" name="action" value="search" />
            <div>{{ __('legacy/forums.text_by_keyword') }}</div>
            <div class="nx-search-form__row">
                <input name="keywords" type="text" value="{{ $search->keywords }}" class="nx-search-form__input" />
                <input name="image" type="image" class="nx-search-form__submit" src="{{ $search->imageUrl }}" alt="Search" />
            </div>
        </form>
    </div>
</div>
@if ($search->searched && $search->hits > 0)
    <x-forum.pager :page="$search->page" :pages="$search->pages" :href="$search->pagerHref()" :items="$search->pagerItems()" label="Pagination top" />
    <x-data-table :caption="__('legacy/forums.head_forum_search')" captionHidden>
        <x-slot:head>
            <thead>
                <tr>
                    <th class="text-center" scope="col">{{ __('legacy/forums.col_post') }}</th>
                    <th class="w-[99%]" scope="col">{{ __('legacy/forums.col_topic') }}</th>
                    <th scope="col">{{ __('legacy/forums.col_forum') }}</th>
                    <th class="whitespace-nowrap" scope="col">{{ __('legacy/forums.col_posted_by') }}</th>
                </tr>
            </thead>
        </x-slot:head>
        @foreach ($search->results as $row)
            <tr>
                <td class="text-center">{{ $row->postId }}</td>
                <td><a href="{{ $search->resultUrl($row->topicId, $row->postId) }}">@if ($row->hlcolor > 0)<b class="nx-hl-{{ $row->hlcolor }}">{{ $row->subject }}</b>@else{{ $row->subject }}@endif</a></td>
                <td class="whitespace-nowrap"><a href="?action=viewforum&amp;forumid={{ $row->forumId }}"><b>{{ $row->forumName }}</b></a></td>
                <td class="whitespace-nowrap"><x-time :value="$row->added" />&nbsp;|&nbsp;{{ $row->poster }}</td>
            </tr>
        @endforeach
    </x-data-table>
    <x-forum.pager :page="$search->page" :pages="$search->pages" :href="$search->pagerHref()" :items="$search->pagerItems()" label="Pagination bottom" />
@endif
