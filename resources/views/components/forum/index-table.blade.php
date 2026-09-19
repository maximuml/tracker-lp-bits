@props(['sections'])
<table data-nx="data" class="nx-forum-table">
    <tbody>
    @foreach ($sections as $section)
        <tr>
            <th class="colhead nx-forum-table__name" scope="col">{{ $section->name }}</th>
            <th class="colhead" scope="col">{{ __('legacy/forums.col_topics') }}</th>
            <th class="colhead" scope="col">{{ __('legacy/forums.col_posts') }}</th>
            <th class="colhead" scope="col">{{ __('legacy/forums.col_last_post') }}</th>
            <th class="colhead" scope="col">{{ __('legacy/forums.col_moderator') }}</th>
        </tr>
        @foreach ($section->forums as $row)
            <x-forum.forum-row :row="$row" />
        @endforeach
    @endforeach
    </tbody>
</table>
