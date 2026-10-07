{{-- Per-user torrent history table (getusertorrentlistajax). Replaces the
     concatenated markup of TorrentAjaxController::torrentListTable(). --}}
<x-data-table :caption="__('getusertorrentlistajax.col_name')" captionHidden>
<x-slot:head>
<thead>
<tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('getusertorrentlistajax.col_type') }}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('getusertorrentlistajax.col_name') }}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('getusertorrentlistajax.col_added') }}</th>@if ($userTorrentListVm->showSize)<th class="bg-nxm-surface-alt font-semibold" scope="col"><img class="size" src="pic/trans.gif" alt="size" title="{{ __('getusertorrentlistajax.title_size') }}" /></th>@endif
@if ($userTorrentListVm->showSeeders)<th class="bg-nxm-surface-alt font-semibold" scope="col"><img class="seeders" src="pic/trans.gif" alt="seeders" title="{{ __('getusertorrentlistajax.title_seeders') }}" /></th>@endif
@if ($userTorrentListVm->showLeechers)<th class="bg-nxm-surface-alt font-semibold" scope="col"><img class="leechers" src="pic/trans.gif" alt="leechers" title="{{ __('getusertorrentlistajax.title_leechers') }}" /></th>@endif
@if ($userTorrentListVm->showUploaded)<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('getusertorrentlistajax.col_uploaded') }}</th>@endif
@if ($userTorrentListVm->showDownloaded)<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('getusertorrentlistajax.col_downloaded') }}</th>@endif
@if ($userTorrentListVm->showRatio)<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('getusertorrentlistajax.col_ratio') }}</th>@endif
@if ($userTorrentListVm->showSeedTime)<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('getusertorrentlistajax.col_se_time') }}</th>@endif
@if ($userTorrentListVm->showLeechTime)<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('getusertorrentlistajax.col_le_time') }}</th>@endif
@if ($userTorrentListVm->showCompletedAt)<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('getusertorrentlistajax.col_time_completed') }}</th>@endif
@if ($userTorrentListVm->showAnonymous)<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('getusertorrentlistajax.col_anonymous') }}</th>@endif
@if ($userTorrentListVm->showClient)<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('getusertorrentlistajax.col_client') }}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">IP</th>@endif
</tr>
</thead>
</x-slot:head>

@foreach ($userTorrentListVm->rows as $row)
<tr @if ($row->rowClass !== null) class="{{ $row->rowClass }}" @endif><td class="align-top px-2.5 py-1.5 whitespace-nowrap"><x-torrent.category-icon :icon="$row->categoryIcon" /></td>
<td class="align-top px-2.5 py-1.5"><a href="{{ $row->nameUrl }}" title="{{ $row->nameTitle }}"><b>{{ $row->displayName }}</b></a>@if ($row->isBanned) <b>(<span class="striking">{{ __('functions.text_banned') }}</span>)</b>@endif<x-torrent.badges :set="$row->badges" /></td>
<td class="align-top px-2.5 py-1.5 whitespace-nowrap text-center">{{ $row->addedDate }}<br/>{{ $row->addedTime }}</td>
@if ($userTorrentListVm->showSize)<td class="align-top px-2.5 py-1.5 text-center">{{ $row->size['value'] }}<br />{{ $row->size['unit'] }}</td>@endif
@if ($userTorrentListVm->showSeeders)<td class="align-top px-2.5 py-1.5 text-center">{{ $row->seeders }}</td>@endif
@if ($userTorrentListVm->showLeechers)<td class="align-top px-2.5 py-1.5 text-center">{{ $row->leechers }}</td>@endif
@if ($userTorrentListVm->showUploaded)<td class="align-top px-2.5 py-1.5 text-center">{{ $row->uploaded['value'] }}<br />{{ $row->uploaded['unit'] }}</td>@endif
@if ($userTorrentListVm->showDownloaded)<td class="align-top px-2.5 py-1.5 text-center">{{ $row->downloaded['value'] }}<br />{{ $row->downloaded['unit'] }}</td>@endif
@if ($userTorrentListVm->showRatio)<td class="align-top px-2.5 py-1.5 text-center">@if ($row->ratioClass !== null)<span class="{{ $row->ratioClass }}">{{ $row->ratioText }}</span>@else{{ $row->ratioText }}@endif</td>@endif
@if ($userTorrentListVm->showSeedTime)<td class="align-top px-2.5 py-1.5 text-center">{{ $row->seedTime }}</td>@endif
@if ($userTorrentListVm->showLeechTime)<td class="align-top px-2.5 py-1.5 text-center">{{ $row->leechTime }}</td>@endif
@if ($userTorrentListVm->showCompletedAt)<td class="align-top px-2.5 py-1.5 text-center"><x-time :value="$row->completedAt" :ago="false" :two-line="true" /></td>@endif
@if ($userTorrentListVm->showAnonymous)<td class="align-top px-2.5 py-1.5 text-center">{{ $row->anonymous }}</td>@endif
@if ($userTorrentListVm->showClient)<td class="align-top px-2.5 py-1.5 text-center">{{ $row->clientAgent }}<br/>{{ $row->clientPort }}</td><td class="align-top px-2.5 py-1.5 text-center">@foreach ($row->clientIps as $ip)<span class="whitespace-nowrap">{{ $ip }}</span>@if (! $loop->last)<br/>@endif @endforeach</td>@endif
</tr>
@endforeach
</x-data-table>
