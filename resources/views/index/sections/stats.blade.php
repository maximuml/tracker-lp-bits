@if($stats['show'])
<h2>{{ $stats['title'] }}</h2>
<table width="100%"><tr><td class="text" align="center">
<table width="60%" class="main" border="1" cellspacing="0" cellpadding="10">
<tr>
<td>{{ $stats['labels']['rowUsersActiveToday'] }}</td><td>{{ $stats['userStats']['activeToday'] }}</td>
<td>{{ $stats['labels']['rowUsersActiveThisWeek'] }}</td><td>{{ $stats['userStats']['activeThisWeek'] }}</td>
</tr>
<tr>
<td>{{ $stats['labels']['rowRegisteredUsers'] }}</td><td>{{ $stats['userStats']['registered'] }}</td>
<td>{{ $stats['labels']['rowUnconfirmedUsers'] }}</td><td>{{ $stats['userStats']['unconfirmed'] }}</td>
</tr>
<tr>
<td>{{ $stats['userStats']['vipLabel'] }}</td><td>{{ $stats['userStats']['vip'] }}</td>
<td>{{ $stats['userStats']['donorsLabel'] }} <img class="star" src="pic/trans.gif" alt="Donor" /></td><td>{{ $stats['userStats']['donors'] }}</td>
</tr>
<tr>
<td>{{ $stats['userStats']['warnedLabel'] }} <img class="warned" src="pic/trans.gif" alt="warned" /></td><td>{{ $stats['userStats']['warned'] }}</td>
<td>{{ $stats['userStats']['bannedLabel'] }} <img class="disabled" src="pic/trans.gif" alt="disabled" /></td><td>{{ $stats['userStats']['banned'] }}</td>
</tr>
<tr>
<td>{{ $stats['userStats']['maleLabel'] }}</td><td>{{ $stats['userStats']['male'] }}</td>
<td>{{ $stats['userStats']['femaleLabel'] }}</td><td>{{ $stats['userStats']['female'] }}</td>
</tr>
<tr><td colspan="4" class="rowhead">&nbsp;</td></tr>
<tr>
<td>{{ $stats['labels']['rowTorrents'] }}</td><td>{{ $stats['torrentStats']['torrents'] }}</td>
<td>{{ $stats['labels']['rowDeadTorrents'] }}</td><td>{{ $stats['torrentStats']['dead'] }}</td>
</tr>
<tr>
<td>{{ $stats['labels']['rowSeeders'] }}</td><td>{{ $stats['torrentStats']['seeders'] }}</td>
<td>{{ $stats['labels']['rowLeechers'] }}</td><td>{{ $stats['torrentStats']['leechers'] }}</td>
</tr>
<tr>
<td>{{ $stats['labels']['rowPeers'] }}</td><td>{{ $stats['torrentStats']['peers'] }}</td>
<td>{{ $stats['labels']['rowSeederLeecherRatio'] }}</td><td>{{ $stats['torrentStats']['ratio'] }}</td>
</tr>
<tr>
<td>{{ $stats['labels']['rowActiveBrowsingUsers'] }}</td><td>{{ $stats['torrentStats']['activeBrowsing'] }}</td>
<td>{{ $stats['labels']['rowTrackerActiveUsers'] }}</td><td>{{ $stats['torrentStats']['trackerActive'] }}</td>
</tr>
<tr>
<td>{{ $stats['labels']['rowTotalSizeOfTorrents'] }}</td><td>{{ $stats['torrentStats']['totalSize'] }}</td>
<td>{{ $stats['labels']['rowTotalUploaded'] }}</td><td>{{ $stats['torrentStats']['totalUploaded'] }}</td>
</tr>
<tr>
<td>{{ $stats['labels']['rowTotalDownloaded'] }}</td><td>{{ $stats['torrentStats']['totalDownloaded'] }}</td>
<td>{{ $stats['labels']['rowTotalData'] }}</td><td>{{ $stats['torrentStats']['totalData'] }}</td>
</tr>
<tr><td colspan="4" class="rowhead">&nbsp;</td></tr>
@php($classRows = $stats['classStats'])
@for($i = 0; $i < count($classRows); $i += 2)
<tr>
<td>{{ $classRows[$i]['label'] }}@if(!empty($classRows[$i]['icon'])) <img class="{{ $classRows[$i]['icon'] }}" src="pic/trans.gif" alt="{{ $classRows[$i]['icon'] }}" />@endif</td><td>{{ $classRows[$i]['value'] }}</td>
@if($i + 1 < count($classRows))
<td>{{ $classRows[$i+1]['label'] }}</td><td>{{ $classRows[$i+1]['value'] }}</td>
@else
<td></td><td></td>
@endif
</tr>
@endfor
</table>
</td></tr></table>
@endif
