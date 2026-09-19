@props(['rows' => [], 'caption' => '', 'what' => ''])
<x-topten.frame :caption="$caption">
<tr>
<td class="colhead">{{ __('legacy/topten.col_rank')}}</td>
<td class="colhead" align="left">{{ __('legacy/topten.col_country')}}</td>
<td class="colhead" align="right">{{ $what }}</td>
</tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left"><div class="nx-row nx-main"><div class="nx-embedded"><img align="center" src="pic/flag/{{ $a['flagpic'] ?? '' }}" alt="" /></div><div class="nx-embedded"><b>{{ $a['name'] ?? '' }}</b></div></div></td><td class="rowfollow" align="right">@if ($what === (__('legacy/topten.col_users'))){{ number_format((float) ($a['num'] ?? 0)) }}@elseif ($what === (__('legacy/topten.col_uploaded'))){{ \App\Support\Format::size((float) ($a['ul'] ?? 0)) }}@elseif ($what === (__('legacy/topten.col_average'))){{ \App\Support\Format::size((float) ($a['ul_avg'] ?? 0)) }}@elseif ($what === (__('legacy/topten.col_ratio'))){{ number_format((float) ($a['r'] ?? 0), 2) }}@endif</td></tr>
@endforeach
</x-topten.frame>
