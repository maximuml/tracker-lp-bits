@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr>
<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('topten.col_rank')}}</th>
<th class="bg-nxm-surface-alt text-left font-semibold" scope="col">{{ __('topten.col_name')}}</th>
<th class="bg-nxm-surface-alt text-right font-semibold" scope="col"><i class="snatched" role="img" aria-label="snatched" title="{{ __('topten.title_sna')}}"></i></th>
<th class="bg-nxm-surface-alt text-right font-semibold" scope="col">{{ __('topten.col_data')}}</th>
<th class="bg-nxm-surface-alt text-right font-semibold" scope="col"><i class="seeders" role="img" aria-label="seeders" title="{{ __('topten.title_se')}}"></i></th>
<th class="bg-nxm-surface-alt text-right font-semibold" scope="col"><i class="leechers" role="img" aria-label="leechers" title="{{ __('topten.col_le')}}"></i></th>
<th class="bg-nxm-surface-alt text-right font-semibold" scope="col">{{ __('topten.col_to')}}</th>
<th class="bg-nxm-surface-alt text-right font-semibold" scope="col">{{ __('topten.col_ratio')}}</th>
</tr>
@foreach ($rows as $a)
<tr><td class="text-center">{{ $loop->iteration }}</td><td><a href="/web/details/{{ (int) ($a['id'] ?? 0) }}&amp;hit=1"><b>{{ $a['name'] ?? '' }}</b></a></td><td class="text-right">{{ number_format((int) ($a['times_completed'] ?? 0)) }}</td><td class="text-right">{{ \App\Support\Format::size((float) ($a['data'] ?? 0)) }}</td><td class="text-right">{{ number_format((int) ($a['seeders'] ?? 0)) }}</td><td class="text-right">{{ number_format((int) ($a['leechers'] ?? 0)) }}</td><td class="text-right">{{ (int) ($a['leechers'] ?? 0) + (int) ($a['seeders'] ?? 0) }}</td><td class="text-right"><x-topten.ratio :up="$a['seeders'] ?? 0" :down="$a['leechers'] ?? 0" :infinite="__('topten.text_inf')" :alwaysWrap="true" /></td>
@endforeach
</x-topten.frame>
