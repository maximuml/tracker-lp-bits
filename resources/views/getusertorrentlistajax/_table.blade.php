{{-- Per-user torrent history table (getusertorrentlistajax). Replaces the
     concatenated markup of TorrentAjaxController::torrentListTable(). --}}
<table data-nx="data"><caption class="nx-sr-only">{{ __('legacy/getusertorrentlistajax.col_name') }}</caption><tr><th class="colhead" scope="col">{{ __('legacy/getusertorrentlistajax.col_type') }}</th><th class="colhead" scope="col">{{ __('legacy/getusertorrentlistajax.col_name') }}</th><th class="colhead" scope="col">{{ __('legacy/getusertorrentlistajax.col_added') }}</th>@if ($userTorrentListVm->showSize)<th class="colhead" scope="col"><img class="size" src="pic/trans.gif" alt="size" title="{{ __('legacy/getusertorrentlistajax.title_size') }}" /></th>@endif
@if ($userTorrentListVm->showSeeders)<th class="colhead" scope="col"><img class="seeders" src="pic/trans.gif" alt="seeders" title="{{ __('legacy/getusertorrentlistajax.title_seeders') }}" /></th>@endif
@if ($userTorrentListVm->showLeechers)<th class="colhead" scope="col"><img class="leechers" src="pic/trans.gif" alt="leechers" title="{{ __('legacy/getusertorrentlistajax.title_leechers') }}" /></th>@endif
@if ($userTorrentListVm->showUploaded)<th class="colhead" scope="col">{{ __('legacy/getusertorrentlistajax.col_uploaded') }}</th>@endif
@if ($userTorrentListVm->showDownloaded)<th class="colhead" scope="col">{{ __('legacy/getusertorrentlistajax.col_downloaded') }}</th>@endif
@if ($userTorrentListVm->showRatio)<th class="colhead" scope="col">{{ __('legacy/getusertorrentlistajax.col_ratio') }}</th>@endif
@if ($userTorrentListVm->showSeedTime)<th class="colhead" scope="col">{{ __('legacy/getusertorrentlistajax.col_se_time') }}</th>@endif
@if ($userTorrentListVm->showLeechTime)<th class="colhead" scope="col">{{ __('legacy/getusertorrentlistajax.col_le_time') }}</th>@endif
@if ($userTorrentListVm->showCompletedAt)<th class="colhead" scope="col">{{ __('legacy/getusertorrentlistajax.col_time_completed') }}</th>@endif
@if ($userTorrentListVm->showAnonymous)<th class="colhead" scope="col">{{ __('legacy/getusertorrentlistajax.col_anonymous') }}</th>@endif
@if ($userTorrentListVm->showClient)<th class="colhead" scope="col">{{ __('legacy/getusertorrentlistajax.col_client') }}</th><th class="colhead" scope="col">IP</th>@endif
</tr>
@foreach ($userTorrentListVm->rows as $row)
<tr @if ($row->rowClass !== null) class="{{ $row->rowClass }}" @endif><td class="rowfollow nowrap"><x-torrent.category-icon :icon="$row->categoryIcon" /></td>
<td class="rowfollow"><a href="{{ $row->nameUrl }}" title="{{ $row->nameTitle }}"><b>{{ $row->displayName }}</b></a>@if ($row->isBanned) <b>(<span class="striking">{{ __('legacy/functions.text_banned') }}</span>)</b>@endif<x-torrent.badges :set="$row->badges" /></td>
<td class="rowfollow nowrap nx-center">{{ $row->addedDate }}<br/>{{ $row->addedTime }}</td>
@if ($userTorrentListVm->showSize)<td class="rowfollow nx-center">{{ $row->size['value'] }}<br />{{ $row->size['unit'] }}</td>@endif
@if ($userTorrentListVm->showSeeders)<td class="rowfollow nx-center">{{ $row->seeders }}</td>@endif
@if ($userTorrentListVm->showLeechers)<td class="rowfollow nx-center">{{ $row->leechers }}</td>@endif
@if ($userTorrentListVm->showUploaded)<td class="rowfollow nx-center">{{ $row->uploaded['value'] }}<br />{{ $row->uploaded['unit'] }}</td>@endif
@if ($userTorrentListVm->showDownloaded)<td class="rowfollow nx-center">{{ $row->downloaded['value'] }}<br />{{ $row->downloaded['unit'] }}</td>@endif
@if ($userTorrentListVm->showRatio)<td class="rowfollow nx-center">@if ($row->ratioClass !== null)<span class="{{ $row->ratioClass }}">{{ $row->ratioText }}</span>@else{{ $row->ratioText }}@endif</td>@endif
@if ($userTorrentListVm->showSeedTime)<td class="rowfollow nx-center">{{ $row->seedTime }}</td>@endif
@if ($userTorrentListVm->showLeechTime)<td class="rowfollow nx-center">{{ $row->leechTime }}</td>@endif
@if ($userTorrentListVm->showCompletedAt)<td class="rowfollow nx-center"><x-time :value="$row->completedAt" :ago="false" :two-line="true" /></td>@endif
@if ($userTorrentListVm->showAnonymous)<td class="rowfollow nx-center">{{ $row->anonymous }}</td>@endif
@if ($userTorrentListVm->showClient)<td class="rowfollow nx-center">{{ $row->clientAgent }}<br/>{{ $row->clientPort }}</td><td class="rowfollow nx-center">@foreach ($row->clientIps as $ip)<span class="nowrap">{{ $ip }}</span>@if (! $loop->last)<br/>@endif @endforeach</td>@endif
</tr>
@endforeach
</table>
