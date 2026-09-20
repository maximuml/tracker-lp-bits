@if ($results->showPager)
{{ $results->pagerTop }}
@endif
<table border="1" cellspacing="0" cellpadding="5" data-nx="data">
<tr><th class="colhead" align="left" scope="col">Name</th>
    <th class="colhead" align="left" scope="col">Ratio</th>
    <th class="colhead" align="left" scope="col">IP</th>
    <th class="colhead" align="left" scope="col">Email</th>
    <th class="colhead" align="left" scope="col">Joined:</th>
    <th class="colhead" align="left" scope="col">Last seen:</th>
    <th class="colhead" align="left" scope="col">Status</th>
    <th class="colhead" align="left" scope="col">Enabled</th>
    <th class="colhead" scope="col">pR</th>
    <th class="colhead" scope="col">pUL</th>
    <th class="colhead" scope="col">pDL</th>
    <th class="colhead" scope="col">History</th></tr>
@foreach ($results->rows as $row)
<tr><td>{{ $row->username }}</td>
    <td>@if ($row->ratio->colorClass !== null)<span class="{{ $row->ratio->colorClass }}">{{ $row->ratio->text }}</span>@else{{ $row->ratio->text }}@endif</td>
    <td>@if ($row->ipBanned)<a href="testip.php?ip={{ $row->ip }}"><span class="nx-color-red"><b>{{ $row->ip }}</b></span></a>@else{{ $row->ip }}@endif</td>
    <td>{{ $row->email }}</td>
    <td><div align="center">{{ $row->added }}</div></td>
    <td><div align="center">{{ $row->lastAccess }}</div></td>
    <td><div align="center">{{ $row->status }}</div></td>
    <td><div align="center">{{ $row->enabled }}</div></td>
    <td><div align="center">@if ($row->peerRatio->colorClass !== null)<span class="{{ $row->peerRatio->colorClass }}">{{ $row->peerRatio->text }}</span>@else{{ $row->peerRatio->text }}@endif</div></td>
    <td><div align="right">{{ $row->peerUploaded }}</div></td>
    <td><div align="right">{{ $row->peerDownloaded }}</div></td>
    <td><div align="center">@if ($row->postCount > 0)<a href="userhistory.php?action=viewposts&amp;id={{ $row->id }}">{{ $row->postCount }}</a>@else{{ $row->postCount }}@endif|@if ($row->commentCount > 0)<a href="userhistory.php?action=viewcomments&amp;id={{ $row->id }}">{{ $row->commentCount }}</a>@else{{ $row->commentCount }}@endif</div></td></tr>
@endforeach
</table>
@if ($results->showPager)
{{ $results->pagerBottom }}
@endif
