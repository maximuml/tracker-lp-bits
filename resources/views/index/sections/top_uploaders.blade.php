@if($topUploaders['show'])
<h2>{{ $topUploaders['title'] }}</h2>
<div class='nx-row tr-top-uploader-tab' title='{{ $topUploaders['toggleHint'] }}'><div class='nx-colhead nx-center nx-grow' data-table='top-uploader-recently'>{{ $topUploaders['recentlyLabel'] }}</div><div class='nx-center nx-grow' data-table='top-uploader-all'>{{ $topUploaders['allLabel'] }}</div></div>

<table data-nx="data" class='top-uploader top-uploader-all nx-hidden' width="100%" border="1" cellspacing="0" cellpadding="5"><tr><td class="colhead" width="">{{ $topUploaders['colAuthor'] }}</td><td class="colhead" align="center">{{ $topUploaders['colCounts'] }}</td><td class="colhead" align="center">{{ $topUploaders['colRanking'] }}</td></tr>
@foreach($topUploaders['allRows'] as $row)
<tr><td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(($row['username'] ?? '')))</td><td align="center">{{ $row['count'] }}</td><td align="center">{{ $row['rank'] }}</td></tr>
@endforeach
</table>

<table data-nx="data" class='top-uploader top-uploader-recently' width="100%" border="1" cellspacing="0" cellpadding="5"><tr><td class="colhead" width="">{{ $topUploaders['colAuthor'] }}</td><td class="colhead" align="center">{{ $topUploaders['colCounts'] }}</td><td class="colhead" align="center">{{ $topUploaders['colRanking'] }}</td></tr>
@foreach($topUploaders['recentRows'] as $row)
<tr><td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(($row['username'] ?? '')))</td><td align="center">{{ $row['count'] }}</td><td align="center">{{ $row['rank'] }}</td></tr>
@endforeach
</table>
@endif
