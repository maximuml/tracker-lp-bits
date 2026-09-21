@props(['rows' => [], 'caption' => '', 'what' => ''])
<x-topten.frame :caption="$caption">
<tr>
<th class="colhead" scope="col">{{ __('legacy/topten.col_rank')}}</th>
<th class="colhead nx-align-left" scope="col">{{ __('legacy/topten.col_country')}}</th>
<th class="colhead nx-align-right" scope="col">{{ $what }}</th>
</tr>
@foreach ($rows as $a)
<tr><td class="rowfollow nx-center">{{ $loop->iteration }}</td><td class="rowfollow"><div class="nx-row nx-main"><div class="nx-embedded"><img src="pic/flag/{{ $a['flagpic'] ?? '' }}" alt="" /></div><div class="nx-embedded"><b>{{ $a['name'] ?? '' }}</b></div></div></td><td class="rowfollow nx-align-right">@if ($what === (__('legacy/topten.col_users'))){{ number_format((float) ($a['num'] ?? 0)) }}@elseif ($what === (__('legacy/topten.col_uploaded'))){{ \App\Support\Format::size((float) ($a['ul'] ?? 0)) }}@elseif ($what === (__('legacy/topten.col_average'))){{ \App\Support\Format::size((float) ($a['ul_avg'] ?? 0)) }}@elseif ($what === (__('legacy/topten.col_ratio'))){{ number_format((float) ($a['r'] ?? 0), 2) }}@endif</td></tr>
@endforeach
</x-topten.frame>
