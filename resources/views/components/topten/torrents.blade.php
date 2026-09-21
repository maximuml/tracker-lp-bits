@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr>
<th class="colhead" scope="col">{{ __('legacy/topten.col_rank')}}</th>
<th class="colhead nx-align-left" scope="col">{{ __('legacy/topten.col_name')}}</th>
<th class="colhead nx-align-right" scope="col"><img class="snatched" src="pic/trans.gif" alt="snatched" title="{{ __('legacy/topten.title_sna')}}" /></th>
<th class="colhead nx-align-right" scope="col">{{ __('legacy/topten.col_data')}}</th>
<th class="colhead nx-align-right" scope="col"><img class="seeders" src="pic/trans.gif" alt="seeders" title="{{ __('legacy/topten.title_se')}}" /></th>
<th class="colhead nx-align-right" scope="col"><img class="leechers" src="pic/trans.gif" alt="leechers" title="{{ __('legacy/topten.col_le')}}" /></th>
<th class="colhead nx-align-right" scope="col">{{ __('legacy/topten.col_to')}}</th>
<th class="colhead nx-align-right" scope="col">{{ __('legacy/topten.col_ratio')}}</th>
</tr>
@foreach ($rows as $a)
<tr><td class="rowfollow nx-center">{{ $loop->iteration }}</td><td class="rowfollow"><a href="details.php?id={{ (int) ($a['id'] ?? 0) }}&amp;hit=1"><b>{{ $a['name'] ?? '' }}</b></a></td><td class="rowfollow nx-align-right">{{ number_format((int) ($a['times_completed'] ?? 0)) }}</td><td class="rowfollow nx-align-right">{{ \App\Support\Format::size((float) ($a['data'] ?? 0)) }}</td><td class="rowfollow nx-align-right">{{ number_format((int) ($a['seeders'] ?? 0)) }}</td><td class="rowfollow nx-align-right">{{ number_format((int) ($a['leechers'] ?? 0)) }}</td><td class="rowfollow nx-align-right">{{ (int) ($a['leechers'] ?? 0) + (int) ($a['seeders'] ?? 0) }}</td><td class="rowfollow nx-align-right"><x-topten.ratio :up="$a['seeders'] ?? 0" :down="$a['leechers'] ?? 0" :infinite="__('legacy/topten.text_inf')" :alwaysWrap="true" /></td>
@endforeach
</x-topten.frame>
