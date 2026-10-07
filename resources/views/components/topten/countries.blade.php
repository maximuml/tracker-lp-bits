@props(['rows' => [], 'caption' => '', 'what' => ''])
<x-topten.frame :caption="$caption">
<tr>
<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('topten.col_rank')}}</th>
<th class="bg-nxm-surface-alt text-left font-semibold" scope="col">{{ __('topten.col_country')}}</th>
<th class="bg-nxm-surface-alt text-right font-semibold" scope="col">{{ $what }}</th>
</tr>
@foreach ($rows as $a)
<tr><td class="text-center">{{ $loop->iteration }}</td><td><div class="flex items-start nx-main"><div class="nx-embedded"><img src="pic/flag/{{ $a['flagpic'] ?? '' }}" alt="" /></div><div class="nx-embedded"><b>{{ $a['name'] ?? '' }}</b></div></div></td><td class="text-right">@if ($what === (__('topten.col_users'))){{ number_format((float) ($a['num'] ?? 0)) }}@elseif ($what === (__('topten.col_uploaded'))){{ \App\Support\Format::size((float) ($a['ul'] ?? 0)) }}@elseif ($what === (__('topten.col_average'))){{ \App\Support\Format::size((float) ($a['ul_avg'] ?? 0)) }}@elseif ($what === (__('topten.col_ratio'))){{ number_format((float) ($a['r'] ?? 0), 2) }}@endif</td></tr>
@endforeach
</x-topten.frame>
