@if ($results->showPager)
{{ $results->pagerTop }}
@endif
<table border="1" cellspacing="0" cellpadding="5" data-nx="data">
<tr><td class="colhead" align="left">Name</td>
    <td class="colhead" align="left">Ratio</td>
    <td class="colhead" align="left">IP</td>
    <td class="colhead" align="left">Email</td>
    <td class="colhead" align="left">Joined:</td>
    <td class="colhead" align="left">Last seen:</td>
    <td class="colhead" align="left">Status</td>
    <td class="colhead" align="left">Enabled</td>
    <td class="colhead">pR</td>
    <td class="colhead">pUL</td>
    <td class="colhead">pDL</td>
    <td class="colhead">History</td></tr>
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
