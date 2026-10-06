@if($stats->show)
@if($stats->todayUsers->count > 0)
<section class="nx-idx-card">
<h2>{{ $stats->labels['rowUsersActiveToday'] }}: {{ number_format($stats->todayUsers->count) }}</h2>
<x-data-table class="nx-today-users"><tr><td><div class="nx-today-users__list">@foreach($stats->todayUsers->cards as $uid => $c)<span class="nx-tip" tabindex="0">{{ \App\Support\UserDisplay::username($uid) }}<span class="nx-card"><span class="nx-card__ava">@if($c->avatar !== '')<img src="{{ $c->avatar }}" alt="" />@else{{ mb_substr($c->username, 0, 1) }}@endif</span><span class="nx-card__body"><b>{{ $c->username }}</b><span class="nx-card__meta">{{ $c->classLabel }} · {{ __('legacy/functions.text_ratio') }} {{ $c->ratio }}</span><span class="nx-card__meta">↑{{ $c->uploaded }} · ↓{{ $c->downloaded }} · {{ $c->lastSeen }}</span><span class="nx-card__links"><a href="/userdetails?id={{ $uid }}">Profile</a> · <a href="/web/sendmessage?receiver={{ $uid }}">PM</a></span></span></span></span>@if(!$loop->last)<span class="nx-today-users__sep"> | </span>@endif@endforeach</div></td></tr></x-data-table>
</section>
@endif
<section class="nx-idx-card">
<div class="nx-stats">
<details class="nx-stats__details">
<summary><img class="plus nx-stats__sign" src="pic/trans.gif" alt="" /><span class="nx-stats__title">{{ $stats->title }}</span></summary>
<div class="p-[10pt] text-center">
<x-data-table :caption="$stats->title" captionHidden class="main mx-auto">
<tr>
<td>{{ $stats->labels['rowUsersActiveToday'] }}</td><td>{{ $stats->userStats['activeToday'] }}</td>
<td>{{ $stats->labels['rowUsersActiveThisWeek'] }}</td><td>{{ $stats->userStats['activeThisWeek'] }}</td>
</tr>
<tr>
<td>{{ $stats->labels['rowRegisteredUsers'] }}</td><td>{{ $stats->userStats['registered'] }}</td>
<td>{{ $stats->labels['rowUnconfirmedUsers'] }}</td><td>{{ $stats->userStats['unconfirmed'] }}</td>
</tr>
<tr>
<td>{{ $stats->userStats['vipLabel'] }}</td><td>{{ $stats->userStats['vip'] }}</td>
<td>{{ $stats->userStats['donorsLabel'] }} <img class="star" src="pic/trans.gif" alt="Donor" /></td><td>{{ $stats->userStats['donors'] }}</td>
</tr>
<tr>
<td>{{ $stats->userStats['warnedLabel'] }} <img class="warned" src="pic/trans.gif" alt="warned" /></td><td>{{ $stats->userStats['warned'] }}</td>
<td>{{ $stats->userStats['bannedLabel'] }} <img class="disabled" src="pic/trans.gif" alt="disabled" /></td><td>{{ $stats->userStats['banned'] }}</td>
</tr>
<tr>
<td>{{ $stats->userStats['maleLabel'] }}</td><td>{{ $stats->userStats['male'] }}</td>
<td>{{ $stats->userStats['femaleLabel'] }}</td><td>{{ $stats->userStats['female'] }}</td>
</tr>
<tr><td colspan="4" class="bg-nxm-surface-alt">&nbsp;</td></tr>
<tr>
<td>{{ $stats->labels['rowTorrents'] }}</td><td>{{ $stats->torrentStats['torrents'] }}</td>
<td>{{ $stats->labels['rowDeadTorrents'] }}</td><td>{{ $stats->torrentStats['dead'] }}</td>
</tr>
<tr>
<td>{{ $stats->labels['rowSeeders'] }}</td><td>{{ $stats->torrentStats['seeders'] }}</td>
<td>{{ $stats->labels['rowLeechers'] }}</td><td>{{ $stats->torrentStats['leechers'] }}</td>
</tr>
<tr>
<td>{{ $stats->labels['rowPeers'] }}</td><td>{{ $stats->torrentStats['peers'] }}</td>
<td>{{ $stats->labels['rowSeederLeecherRatio'] }}</td><td>{{ $stats->torrentStats['ratio'] }}</td>
</tr>
<tr>
<td>{{ $stats->labels['rowActiveBrowsingUsers'] }}</td><td>{{ $stats->torrentStats['activeBrowsing'] }}</td>
<td>{{ $stats->labels['rowTrackerActiveUsers'] }}</td><td>{{ $stats->torrentStats['trackerActive'] }}</td>
</tr>
<tr>
<td>{{ $stats->labels['rowTotalSizeOfTorrents'] }}</td><td>{{ $stats->torrentStats['totalSize'] }}</td>
<td>{{ $stats->labels['rowTotalUploaded'] }}</td><td>{{ $stats->torrentStats['totalUploaded'] }}</td>
</tr>
<tr>
<td>{{ $stats->labels['rowTotalDownloaded'] }}</td><td>{{ $stats->torrentStats['totalDownloaded'] }}</td>
<td>{{ $stats->labels['rowTotalData'] }}</td><td>{{ $stats->torrentStats['totalData'] }}</td>
</tr>
<tr><td colspan="4" class="bg-nxm-surface-alt">&nbsp;</td></tr>
@foreach (array_chunk($stats->classStats, 2) as $pair)
<tr>
<td>{{ $pair[0]->label }}@if($pair[0]->icon !== null) <img class="{{ $pair[0]->icon }}" src="pic/trans.gif" alt="{{ $pair[0]->icon }}" />@endif</td><td>{{ $pair[0]->value }}</td>
@if(isset($pair[1]))
<td>{{ $pair[1]->label }}</td><td>{{ $pair[1]->value }}</td>
@else
<td></td><td></td>
@endif
</tr>
@endforeach
</x-data-table>
</div>
</details>
<x-data-table class="main nx-stats-strip">
<tr>
    <td><span class="nx-stats-strip__label">{{ $stats->labels['rowUsersActiveToday'] }}</span><b>{{ $stats->userStats['activeToday'] }}</b></td>
    <td><span class="nx-stats-strip__label">{{ $stats->labels['rowRegisteredUsers'] }}</span><b>{{ $stats->userStats['registered'] }}</b></td>
    <td><span class="nx-stats-strip__label">{{ $stats->labels['rowTorrents'] }}</span><b>{{ $stats->torrentStats['torrents'] }}</b></td>
    <td><span class="nx-stats-strip__label">{{ $stats->labels['rowPeers'] }}</span><b>{{ $stats->torrentStats['peers'] }}</b></td>
    <td><span class="nx-stats-strip__label">{{ $stats->labels['rowTotalSizeOfTorrents'] }}</span><b>{{ $stats->torrentStats['totalSize'] }}</b></td>
</tr>
</x-data-table>
</div>
</section>
@endif
