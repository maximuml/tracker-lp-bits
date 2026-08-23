@if($topUploaders['show'])
<h2>{{ $topUploaders['title'] }}</h2>
<table width='100%'><tr class='tr-top-uploader-tab' title='{{ $topUploaders['toggleHint'] }}'><td class='colhead' align='center' data-table='top-uploader-recently'>{{ $topUploaders['recentlyLabel'] }}</td><td align='center' data-table='top-uploader-all'>{{ $topUploaders['allLabel'] }}</td></tr></table>

<table class='top-uploader top-uploader-all' width="100%" border="1" cellspacing="0" cellpadding="5" style='display: none'><tr><td class="colhead" width="">{{ $topUploaders['colAuthor'] }}</td><td class="colhead" align="center">{{ $topUploaders['colCounts'] }}</td><td class="colhead" align="center">{{ $topUploaders['colRanking'] }}</td></tr>
@foreach($topUploaders['allRows'] as $row)
<tr><td>{!! $row['username'] !!}</td><td align="center">{{ $row['count'] }}</td><td align="center">{{ $row['rank'] }}</td></tr>
@endforeach
</table>

<table class='top-uploader top-uploader-recently' width="100%" border="1" cellspacing="0" cellpadding="5"><tr><td class="colhead" width="">{{ $topUploaders['colAuthor'] }}</td><td class="colhead" align="center">{{ $topUploaders['colCounts'] }}</td><td class="colhead" align="center">{{ $topUploaders['colRanking'] }}</td></tr>
@foreach($topUploaders['recentRows'] as $row)
<tr><td>{!! $row['username'] !!}</td><td align="center">{{ $row['count'] }}</td><td align="center">{{ $row['rank'] }}</td></tr>
@endforeach
</table>
@endif
