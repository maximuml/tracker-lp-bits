@if ($results->showPager)
<x-pagination :page="$results->pager['page']" :pages="$results->pager['pages']" :href="$results->pager['href']" :prev-label="$results->pager['prevLabel']" :next-label="$results->pager['nextLabel']" :zero-based="$results->pager['zeroBased']" label="Pagination top" />
@endif
<x-data-table :caption="'Search results'" :caption-hidden="true">
    <x-slot:head>
        <thead>
            <tr><th scope="col">{{ __('label.name') }}</th>
                <th scope="col">{{ __('topten.col_ratio') }}</th>
                <th scope="col">IP</th>
                <th scope="col">{{ __('label.email') }}</th>
                <th scope="col">Joined:</th>
                <th scope="col">{{ __('usersearch.last_seen') }}</th>
                <th scope="col">{{ __('label.status') }}</th>
                <th scope="col">{{ __('label.enabled') }}</th>
                <th class="text-center" scope="col">pR</th>
                <th class="text-center" scope="col">pUL</th>
                <th class="text-center" scope="col">pDL</th>
                <th class="text-center" scope="col">{{ __('mybonus.text_history') }}</th></tr>
        </thead>
    </x-slot:head>
    @foreach ($results->rows as $row)
        <tr><td>{{ $row->username }}</td>
            <td>@if ($row->ratio->colorClass !== null)<span class="{{ $row->ratio->colorClass }}">{{ $row->ratio->text }}</span>@else{{ $row->ratio->text }}@endif</td>
            <td>@if ($row->ipBanned)<a href="/web/testip?ip={{ $row->ip }}"><span class="text-nxm-danger"><b>{{ $row->ip }}</b></span></a>@else{{ $row->ip }}@endif</td>
            <td>{{ $row->email }}</td>
            <td><div class="text-center">{{ $row->added }}</div></td>
            <td><div class="text-center">{{ $row->lastAccess }}</div></td>
            <td><div class="text-center">{{ $row->status }}</div></td>
            <td><div class="text-center">{{ $row->enabled }}</div></td>
            <td><div class="text-center">@if ($row->peerRatio->colorClass !== null)<span class="{{ $row->peerRatio->colorClass }}">{{ $row->peerRatio->text }}</span>@else{{ $row->peerRatio->text }}@endif</div></td>
            <td><div class="text-right">{{ $row->peerUploaded }}</div></td>
            <td><div class="text-right">{{ $row->peerDownloaded }}</div></td>
            <td><div class="text-center">@if ($row->postCount > 0)<a href="/web/userhistory?action=viewposts&amp;id={{ $row->id }}">{{ $row->postCount }}</a>@else{{ $row->postCount }}@endif|@if ($row->commentCount > 0)<a href="/web/userhistory?action=viewcomments&amp;id={{ $row->id }}">{{ $row->commentCount }}</a>@else{{ $row->commentCount }}@endif</div></td></tr>
    @endforeach
</x-data-table>
@if ($results->showPager)
<x-pagination :page="$results->pager['page']" :pages="$results->pager['pages']" :href="$results->pager['href']" :prev-label="$results->pager['prevLabel']" :next-label="$results->pager['nextLabel']" :zero-based="$results->pager['zeroBased']" label="Pagination bottom" />
@endif
