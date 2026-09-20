@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr><th class="colhead" scope="col">{{ __('legacy/topten.col_rank')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_name')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_number')}}</th></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left">{{ $a['lang_name'] ?? '' }}</td><td class="rowfollow" align="right">{{ number_format((int) ($a['lang_num'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
