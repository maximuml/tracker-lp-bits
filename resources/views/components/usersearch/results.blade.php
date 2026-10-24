@if ($results->showPager)
{{ $results->pagerTop }}
@endif
<x-data-table :caption="'Search results'" :caption-hidden="true">
    <x-slot:head>
        <thead>
            <tr><th scope="col">Name</th>
                <th scope="col">Ratio</th>
                <th scope="col">IP</th>
                <th scope="col">Email</th>
                <th scope="col">Joined:</th>
                <th scope="col">Last seen:</th>
                <th scope="col">Status</th>
                <th scope="col">Enabled</th>
                <th class="text-center" scope="col">pR</th>
                <th class="text-center" scope="col">pUL</th>
                <th class="text-center" scope="col">pDL</th>
                <th class="text-center" scope="col">History</th></tr>
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
{{ $results->pagerBottom }}
@endif
