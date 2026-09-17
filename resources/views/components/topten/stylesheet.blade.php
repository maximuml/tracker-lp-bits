@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr><td class="colhead">{{ __('legacy/topten.col_rank')}}</td><td class="colhead">{{ __('legacy/topten.col_name')}}</td><td class="colhead">{{ __('legacy/topten.col_number')}}</td></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left">{{ $a['stylesheet_name'] ?? '' }}</td><td class="rowfollow" align="right">{{ number_format((int) ($a['stylesheet_num'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
