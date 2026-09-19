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
    <x-forum.pager :page="$search->page" :pages="$search->pages" :href="$search->pagerHref()" :items="$search->pagerItems()" />
    <table data-nx="data" class="nx-forum-table">
        <tbody>
        <tr>
            <th class="colhead nx-center" scope="col">{{ __('legacy/forums.col_post') }}</th>
            <th class="colhead nx-forum-table__name" scope="col">{{ __('legacy/forums.col_topic') }}</th>
            <th class="colhead" scope="col">{{ __('legacy/forums.col_forum') }}</th>
            <th class="colhead nx-nowrap" scope="col">{{ __('legacy/forums.col_posted_by') }}</th>
        </tr>
        @foreach ($search->results as $row)
            <tr>
                <td class="rowfollow nx-center">{{ $row->postId }}</td>
                <td class="rowfollow"><a href="{{ $search->resultUrl($row->topicId, $row->postId) }}">@if ($row->hlcolor > 0)<b class="nx-hl-{{ $row->hlcolor }}">{{ $row->subject }}</b>@else{{ $row->subject }}@endif</a></td>
                <td class="rowfollow nx-nowrap"><a href="?action=viewforum&amp;forumid={{ $row->forumId }}"><b>{{ $row->forumName }}</b></a></td>
                <td class="rowfollow nx-nowrap"><x-time :value="$row->added" />&nbsp;|&nbsp;{{ $row->poster }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <x-forum.pager :page="$search->page" :pages="$search->pages" :href="$search->pagerHref()" :items="$search->pagerItems()" />
@endif
