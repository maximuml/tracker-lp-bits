@props(['sections'])
<x-data-table :caption="trim(__('legacy/forums.text_forums'))" captionHidden>
    @foreach ($sections as $section)
        <tr>
            <th class="w-[99%] bg-nxm-surface-alt font-semibold" scope="col">{{ $section->name }}</th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/forums.col_topics') }}</th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/forums.col_posts') }}</th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/forums.col_last_post') }}</th>
            <th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/forums.col_moderator') }}</th>
        </tr>
        @foreach ($section->forums as $row)
            <x-forum.forum-row :row="$row" />
        @endforeach
    @endforeach
</x-data-table>
